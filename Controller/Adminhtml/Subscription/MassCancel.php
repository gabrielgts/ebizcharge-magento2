<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription;

class MassCancel extends AbstractMassAction
{
    protected function actOn(int $subscriptionId): void
    {
        $this->lifecycle->cancel($subscriptionId, 'admin (mass)');
    }

    protected function successLabel(): string
    {
        return (string) __('Cancelled');
    }
}
