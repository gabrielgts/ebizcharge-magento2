<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Subscription;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;

class Cancel extends AbstractCustomerAction
{
    protected function doAction(SubscriptionInterface $subscription): void
    {
        $this->lifecycle->cancel((int) $subscription->getEntityId(), 'customer');
        $this->messageManager->addSuccessMessage(__('Your subscription has been cancelled.'));
    }
}
