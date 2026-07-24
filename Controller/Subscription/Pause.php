<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Subscription;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;

class Pause extends AbstractCustomerAction
{
    protected function doAction(SubscriptionInterface $subscription): void
    {
        $this->lifecycle->pause((int) $subscription->getEntityId(), 'customer');
        $this->messageManager->addSuccessMessage(
            __('Your subscription has been paused. No further charges will be made until you resume it.')
        );
    }
}
