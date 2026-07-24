<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Observer\Adminhtml;

use Gtstudio\Ebizcharge\Service\CustomerIdentityStorage;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/** Validates a manually edited CustomerID before Magento saves the customer. */
class PrepareCustomerIdentitySave implements ObserverInterface
{
    public const CHANGE_FLAG = 'gtstudio_ebizcharge_identity_changed';

    public function __construct(private readonly CustomerIdentityStorage $storage)
    {
    }

    public function execute(Observer $observer): void
    {
        $customer = $observer->getData('customer');
        $request = $observer->getData('request');
        $customerId = (int) ($customer?->getId() ?? 0);
        $postedIdentity = $request?->getPost('ebizcharge_identity');
        if ($customerId <= 0
            || !is_array($postedIdentity)
            || !array_key_exists('ebiz_customer_id', $postedIdentity)
        ) {
            return;
        }

        $newCustomerId = trim((string) $postedIdentity['ebiz_customer_id']);
        $current = $this->storage->get($customerId);
        $currentConfiguredId = $current->usesStoredMapping ? $current->customerId : '';
        if ($newCustomerId === $currentConfiguredId) {
            return;
        }

        $this->storage->assertCustomerIdAvailable($newCustomerId, $customerId);
        $request->setParam(self::CHANGE_FLAG, 1);
    }
}
