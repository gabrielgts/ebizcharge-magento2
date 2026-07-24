<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription;

class MassChargeNow extends AbstractMassAction
{
    protected function actOn(int $subscriptionId): void
    {
        $this->lifecycle->chargeNow($subscriptionId);
    }

    protected function successLabel(): string
    {
        return (string) __('Charge queued');
    }
}
