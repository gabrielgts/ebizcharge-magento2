<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Observer;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Setup\Patch\Data\AddSubscriptionAttributesPatch;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;

/** Rejects recurring-product checkout before payment unless a reusable card can be attached. */
class ValidateSubscriptionCheckout implements ObserverInterface
{
    private const SUPPORTED_PRODUCT_TYPES = [
        ProductType::TYPE_SIMPLE,
        ProductType::TYPE_VIRTUAL,
    ];

    public function __construct(private readonly ProductRepositoryInterface $productRepository)
    {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getData('quote');
        if (!$quote instanceof Quote || $this->isRenewal($quote) || !$this->hasSubscriptionItems($quote)) {
            return;
        }
        if ((int) ($quote->getCustomerId() ?? 0) <= 0) {
            throw new LocalizedException(__('Please sign in before purchasing a subscription product.'));
        }

        $payment = $quote->getPayment();
        $method = (string) $payment->getMethod();
        $newCardWillBeSaved = $method === Config::METHOD_CODE
            && (bool) $payment->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE);
        $savedCardSelected = $method === Config::METHOD_CODE_VAULT
            && trim((string) $payment->getAdditionalInformation(PaymentTokenInterface::PUBLIC_HASH)) !== '';

        if (!$newCardWillBeSaved && !$savedCardSelected) {
            throw new LocalizedException(__(
                'Subscription products require an EBizCharge card saved to your account.'
            ));
        }
    }

    private function isRenewal(Quote $quote): bool
    {
        return (bool) $quote->getPayment()->getAdditionalInformation('gtstudio_recurring_charge');
    }

    private function hasSubscriptionItems(Quote $quote): bool
    {
        $hasSubscriptionItems = false;
        foreach ($quote->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            if ($product === null || $product->getData(AddSubscriptionAttributesPatch::ATTR_SUBSCRIBABLE) === null) {
                try {
                    $product = $this->productRepository->getById(
                        (int) $item->getProductId(),
                        false,
                        (int) $quote->getStoreId()
                    );
                } catch (\Throwable) {
                    continue;
                }
            }
            if (!(bool) $product->getData(AddSubscriptionAttributesPatch::ATTR_SUBSCRIBABLE)) {
                continue;
            }
            $hasSubscriptionItems = true;
            if (!in_array($product->getTypeId(), self::SUPPORTED_PRODUCT_TYPES, true)) {
                throw new LocalizedException(__(
                    'Subscription product %1 uses an unsupported product type.',
                    $item->getName()
                ));
            }
            $optionIds = $item->getOptionByCode('option_ids');
            if ($optionIds !== null && trim((string) $optionIds->getValue()) !== '') {
                throw new LocalizedException(__(
                    'Subscription product %1 uses options that cannot be renewed automatically.',
                    $item->getName()
                ));
            }
            if (trim((string) $product->getData(AddSubscriptionAttributesPatch::ATTR_FREQUENCY)) === '') {
                throw new LocalizedException(__('Subscription product %1 has no billing frequency.', $item->getName()));
            }
        }
        return $hasSubscriptionItems;
    }
}
