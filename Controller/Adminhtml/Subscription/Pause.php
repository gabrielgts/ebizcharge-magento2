<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription;

class Pause extends AbstractAction
{
    protected function doAction(int $subscriptionId): void
    {
        $this->lifecycle->pause($subscriptionId, 'admin');
        $this->messageManager->addSuccessMessage(__('Subscription #%1 paused.', $subscriptionId));
    }
}
