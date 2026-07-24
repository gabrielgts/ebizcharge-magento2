<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription;

class MassPause extends AbstractMassAction
{
    protected function actOn(int $subscriptionId): void
    {
        $this->lifecycle->pause($subscriptionId, 'admin (mass)');
    }

    protected function successLabel(): string
    {
        return (string) __('Paused');
    }
}
