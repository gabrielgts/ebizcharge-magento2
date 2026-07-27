<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Observer;

use DateTimeImmutable;
use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionRepositoryInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionScheduleInterface;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Model\SubscriptionFactory;
use Gtstudio\Ebizcharge\Model\SubscriptionItemFactory;
use Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionItem as SubscriptionItemResource;
use Gtstudio\Ebizcharge\Setup\Patch\Data\AddSubscriptionAttributesPatch;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

/** Creates subscriptions from eligible customer orders with Vault tokens. */
class CreateSubscriptionFromOrder implements ObserverInterface
{
    public function __construct(
        private readonly SubscriptionFactory $subscriptionFactory,
        private readonly SubscriptionItemFactory $subscriptionItemFactory,
        private readonly SubscriptionItemResource $itemResource,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly SubscriptionScheduleInterface $schedule,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Logger $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var OrderInterface|null $order */
        $order = $observer->getEvent()->getData('order');
        if ($order === null || !($order instanceof OrderInterface)) {
            return;
        }

        $payment = $order->getPayment();
        if ($payment !== null && (bool) $payment->getAdditionalInformation('gtstudio_recurring_charge')) {
            // Never create subscriptions from generated renewal orders.
            return;
        }

        $customerId = (int) ($order->getCustomerId() ?? 0);
        if ($customerId === 0) {
            return;
        }

        $itemsByFrequency = $this->groupItemsByFrequency($order);
        if ($itemsByFrequency === []) {
            return;
        }

        $tokenId = $this->extractVaultTokenId($order);
        if ($tokenId === null) {
            if ($payment !== null) {
                $payment->setAdditionalInformation('subscription_creation_status', 'missing_vault_token');
            }
            $this->logger->warning('subscription.creation_skipped', [
                'order_id' => $order->getEntityId(),
                'reason' => 'missing_vault_token',
            ]);
            return;
        }

        foreach ($itemsByFrequency as $frequency => $items) {
            $this->createSubscription($order, $customerId, $tokenId, (string) $frequency, $items);
        }
    }

    /** Returns the order payment Vault token ID. */
    private function extractVaultTokenId(OrderInterface $order): ?int
    {
        $payment = $order->getPayment();
        if ($payment === null) {
            return null;
        }
        $extension = $payment->getExtensionAttributes();
        if ($extension === null) {
            return null;
        }
        $token = $extension->getVaultPaymentToken();
        if ($token === null || !$token->getEntityId()) {
            return null;
        }
        return (int) $token->getEntityId();
    }

    /** @return array<string,OrderItemInterface[]> */
    private function groupItemsByFrequency(OrderInterface $order): array
    {
        $grouped = [];
        foreach ($order->getItems() as $item) {
            if ($item->getParentItem() !== null) {
                continue;
            }
            try {
                $product = $this->productRepository->getById((int) $item->getProductId());
            } catch (\Throwable) {
                continue;
            }
            if (!(bool) $product->getData(AddSubscriptionAttributesPatch::ATTR_SUBSCRIBABLE)) {
                continue;
            }
            $frequency = (string) $product->getData(AddSubscriptionAttributesPatch::ATTR_FREQUENCY);
            if ($frequency === '') {
                continue;
            }
            $grouped[$frequency][] = $item;
        }
        return $grouped;
    }

    /** @param OrderItemInterface[] $items */
    private function createSubscription(
        OrderInterface $order,
        int $customerId,
        int $tokenId,
        string $frequency,
        array $items
    ): void {
        $totalAmount = 0.0;
        foreach ($items as $item) {
            $qty = (float) $item->getQtyOrdered();
            if ($qty <= 0) {
                continue;
            }
            $totalAmount += max(0.0, (float) $item->getRowTotal() - (float) $item->getDiscountAmount());
        }
        if ($totalAmount <= 0) {
            return;
        }

        $today = new DateTimeImmutable('today');
        $nextBillDate = $this->schedule->computeNextBillDate($frequency, $today);

        $subscription = $this->subscriptionFactory->create();
        $subscription->setCustomerId($customerId);
        $subscription->setVaultPaymentTokenId($tokenId);
        $subscription->setStatus(SubscriptionInterface::STATUS_ACTIVE);
        $subscription->setFrequency($frequency);
        $subscription->setAmount($totalAmount);
        $subscription->setCurrencyCode((string) $order->getOrderCurrencyCode());
        $subscription->setStoreId((int) $order->getStoreId());
        $subscription->setNextBillDate($nextBillDate->format('Y-m-d'));
        $subscription->setStartDate($today->format('Y-m-d'));
        $subscription->setCompletedCycles(1);
        $subscription->setFailureCount(0);
        $subscription->setSourceOrderId((int) $order->getEntityId());
        $subscription->setLastChargedAt(date('Y-m-d H:i:s'));
        $subscription->setLabel($this->buildLabel($items, $frequency));

        $this->subscriptionRepository->save($subscription);

        foreach ($items as $orderItem) {
            $line = $this->subscriptionItemFactory->create();
            $line->setSubscriptionId((int) $subscription->getEntityId());
            $line->setProductId((int) $orderItem->getProductId());
            $line->setSku((string) $orderItem->getSku());
            $line->setQty((float) $orderItem->getQtyOrdered());
            $qty = (float) $orderItem->getQtyOrdered();
            if ($qty <= 0) {
                continue;
            }
            $lockedRowTotal = max(
                0.0,
                (float) $orderItem->getRowTotal() - (float) $orderItem->getDiscountAmount()
            );
            $line->setPrice($lockedRowTotal / $qty);
            $this->itemResource->save($line);
        }

        $this->logger->info('subscription.created_from_order', [
            'subscription_id' => $subscription->getEntityId(),
            'order_id' => $order->getEntityId(),
            'frequency' => $frequency,
            'amount' => $totalAmount,
            'next_bill_date' => $subscription->getNextBillDate(),
        ]);
    }

    /** @param OrderItemInterface[] $items */
    private function buildLabel(array $items, string $frequency): string
    {
        $names = [];
        foreach ($items as $item) {
            $names[] = (string) $item->getName();
        }
        return sprintf('%s — %s', implode(', ', $names), ucfirst($frequency));
    }
}
