<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription;

class Resume extends AbstractAction
{
    protected function doAction(int $subscriptionId): void
    {
        $this->lifecycle->resume($subscriptionId);
        $this->messageManager->addSuccessMessage(__('Subscription #%1 resumed.', $subscriptionId));
    }
}
