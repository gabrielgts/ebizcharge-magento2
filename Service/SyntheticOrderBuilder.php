<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionItem\Collection as SubscriptionItemCollection;
use Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionItem\CollectionFactory as SubscriptionItemCollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Builds a Magento quote from a subscription, places it as an order through the standard
 * CartManagementInterface flow. The standard placement triggers the vault command pool we built
 * in Phase 3 — recurring charges run through the exact same code path as a manual storefront
 * vault payment, so there is one set of payment plumbing to maintain.
 *
 * Address handling:
 *  - Prefers the customer's default billing/shipping addresses
 *  - Falls back to the source order's addresses if no defaults
 *  - Throws if neither is available (we refuse to silently ship to nowhere)
 */
class SyntheticOrderBuilder
{
    public function __construct(
        private readonly CartManagementInterface $cartManagement,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SubscriptionItemCollectionFactory $itemCollectionFactory,
        private readonly RecurringVaultTokenValidator $tokenValidator,
        private readonly CorrelationIdProvider $correlationIdProvider,
        private readonly Logger $logger
    ) {
    }

    /**
     * Place an order from the subscription. Returns the new order id.
     *
     * @throws LocalizedException
     */
    public function placeOrder(SubscriptionInterface $subscription, string $correlationId): int
    {
        $this->correlationIdProvider->set($correlationId);
        $customer = $this->customerRepository->getById($subscription->getCustomerId());
        $token = $this->tokenValidator->validate($subscription);
        $methodCode = 'gtstudio_ebizcharge_cc_vault';

        $cartId = $this->cartManagement->createEmptyCartForCustomer($customer->getId());
        /** @var Quote $quote */
        $quote = $this->cartRepository->getActive($cartId);
        $quote->setStoreId($subscription->getStoreId());
        $quote->setCurrency();
        $quote->setQuoteCurrencyCode($subscription->getCurrencyCode());

        $this->addSubscriptionItems($quote, $subscription);
        $this->applyAddresses($quote, $customer, $subscription);
        $this->applyShippingMethod($quote);
        $this->applyVaultPaymentMethod(
            $quote,
            $methodCode,
            (string) $token->getPublicHash(),
            (int) $subscription->getEntityId(),
            $correlationId
        );

        $quote->collectTotals();
        $this->cartRepository->save($quote);

        $orderId = (int) $this->cartManagement->placeOrder($cartId);

        return $orderId;
    }

    private function addSubscriptionItems(Quote $quote, SubscriptionInterface $subscription): void
    {
        /** @var SubscriptionItemCollection $items */
        $items = $this->itemCollectionFactory->create();
        $items->addFieldToFilter('subscription_id', (int) $subscription->getEntityId());

        if ($items->getSize() === 0) {
            throw new LocalizedException(__('Subscription %1 has no line items.', $subscription->getEntityId()));
        }

        foreach ($items as $line) {
            $product = $this->productRepository->getById((int) $line->getProductId());
            $result = $quote->addProduct($product, (float) $line->getQty());
            if (!$result instanceof QuoteItem) {
                throw new LocalizedException(__(
                    'Could not add subscription item %1 to the renewal quote.',
                    $line->getSku()
                ));
            }
            $lockedPrice = (float) $line->getPrice();
            $result->setCustomPrice($lockedPrice);
            $result->setOriginalCustomPrice($lockedPrice);
            $result->getProduct()->setIsSuperMode(true);
        }
    }

    private function applyAddresses(Quote $quote, $customer, SubscriptionInterface $subscription): void
    {
        $defaultBillingId = $customer->getDefaultBilling();
        $defaultShippingId = $customer->getDefaultShipping();
        $billing = null;
        $shipping = null;

        if ($defaultBillingId) {
            try {
                $billing = $this->addressRepository->getById((int) $defaultBillingId);
            } catch (\Throwable) {
                $billing = null;
            }
        }
        if ($defaultShippingId) {
            try {
                $shipping = $this->addressRepository->getById((int) $defaultShippingId);
            } catch (\Throwable) {
                $shipping = null;
            }
        }

        // Fall back to the source order's addresses if customer has no defaults
        if (($billing === null || $shipping === null) && $subscription->getSourceOrderId() !== null) {
            try {
                $sourceOrder = $this->orderRepository->get($subscription->getSourceOrderId());
                if ($billing === null) {
                    $quote->getBillingAddress()->addData($this->mapOrderAddress($sourceOrder->getBillingAddress()));
                }
                if ($shipping === null && $sourceOrder->getShippingAddress() !== null) {
                    $quote->getShippingAddress()->addData($this->mapOrderAddress($sourceOrder->getShippingAddress()));
                }
            } catch (\Throwable $e) {
                $this->logger->warning('subscription.synthetic_order.fallback_address_failed', [
                    'subscription_id' => $subscription->getEntityId(),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($billing !== null) {
            $quote->getBillingAddress()->importCustomerAddressData($billing);
        }
        if ($shipping !== null) {
            $quote->getShippingAddress()->importCustomerAddressData($shipping);
        }

        if (!$quote->getBillingAddress()->getCountryId()) {
            throw new LocalizedException(__('No billing address available for subscription %1.', $subscription->getEntityId()));
        }
    }

    private function mapOrderAddress($orderAddress): array
    {
        return [
            'firstname' => $orderAddress->getFirstname(),
            'lastname' => $orderAddress->getLastname(),
            'company' => $orderAddress->getCompany(),
            'street' => $orderAddress->getStreet(),
            'city' => $orderAddress->getCity(),
            'region_id' => $orderAddress->getRegionId(),
            'region' => $orderAddress->getRegion(),
            'postcode' => $orderAddress->getPostcode(),
            'country_id' => $orderAddress->getCountryId(),
            'telephone' => $orderAddress->getTelephone(),
            'email' => $orderAddress->getEmail(),
        ];
    }

    private function applyShippingMethod(Quote $quote): void
    {
        if ($quote->isVirtual()) {
            return;
        }
        $shipping = $quote->getShippingAddress();
        $shipping->setCollectShippingRates(true);
        $shipping->collectShippingRates();

        $rates = $shipping->getAllShippingRates();
        if ($rates) {
            // Cheapest available rate. Admin-configurable override is a future improvement.
            $cheapest = null;
            foreach ($rates as $rate) {
                if ($cheapest === null || (float) $rate->getPrice() < (float) $cheapest->getPrice()) {
                    $cheapest = $rate;
                }
            }
            if ($cheapest !== null) {
                $shipping->setShippingMethod($cheapest->getCode());
            }
        }
    }

    private function applyVaultPaymentMethod(
        Quote $quote,
        string $methodCode,
        string $publicHash,
        int $subscriptionId,
        string $correlationId
    ): void {
        $quote->setPaymentMethod($methodCode);
        $quote->getPayment()->importData([
            'method' => $methodCode,
            'additional_data' => ['public_hash' => $publicHash],
        ]);
        // These must exist before placeOrder(): the Vault builder and SOAP client execute during
        // placement, and the order-creation observer must recognize this as a renewal.
        $quote->getPayment()->setAdditionalInformation('gtstudio_recurring_charge', true);
        $quote->getPayment()->setAdditionalInformation('gtstudio_subscription_id', $subscriptionId);
        $quote->getPayment()->setAdditionalInformation('gtstudio_correlation_id', $correlationId);
        $quote->setCheckoutMethod('customer');
    }
}
