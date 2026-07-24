<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Subscription;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;

class Resume extends AbstractCustomerAction
{
    protected function doAction(SubscriptionInterface $subscription): void
    {
        $this->lifecycle->resume((int) $subscription->getEntityId());
        $this->messageManager->addSuccessMessage(__('Your subscription has been resumed.'));
    }
}
