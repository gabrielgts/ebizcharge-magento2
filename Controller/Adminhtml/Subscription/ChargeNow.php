<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription;

class ChargeNow extends AbstractAction
{
    protected function doAction(int $subscriptionId): void
    {
        $chargeId = $this->lifecycle->chargeNow($subscriptionId);
        $this->messageManager->addSuccessMessage(__(
            'Charge queued for subscription #%1 (charge #%2). Will run at the next charging cron.',
            $subscriptionId,
            $chargeId
        ));
    }
}
