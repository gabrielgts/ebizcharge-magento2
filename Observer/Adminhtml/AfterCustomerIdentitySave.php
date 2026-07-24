<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Observer\Adminhtml;

use Gtstudio\Ebizcharge\Service\CustomerIdentityStorage;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/** Clears identifiers derived from CustomerID only after the customer edit saved successfully. */
class AfterCustomerIdentitySave implements ObserverInterface
{
    public function __construct(private readonly CustomerIdentityStorage $storage)
    {
    }

    public function execute(Observer $observer): void
    {
        $request = $observer->getData('request');
        if (!(bool) $request?->getParam(PrepareCustomerIdentitySave::CHANGE_FLAG)) {
            return;
        }
        $customerId = (int) ($observer->getData('customer')?->getId() ?? 0);
        if ($customerId > 0) {
            $postedIdentity = $request->getPost('ebizcharge_identity');
            $this->storage->setCustomerId(
                $customerId,
                is_array($postedIdentity) ? (string) ($postedIdentity['ebiz_customer_id'] ?? '') : ''
            );
        }
    }
}
