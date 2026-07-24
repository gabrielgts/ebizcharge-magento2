<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription;

class Cancel extends AbstractAction
{
    protected function doAction(int $subscriptionId): void
    {
        $this->lifecycle->cancel($subscriptionId, 'admin');
        $this->messageManager->addSuccessMessage(__('Subscription #%1 cancelled.', $subscriptionId));
    }
}
