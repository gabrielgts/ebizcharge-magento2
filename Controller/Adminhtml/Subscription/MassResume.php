<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription;

class MassResume extends AbstractMassAction
{
    protected function actOn(int $subscriptionId): void
    {
        $this->lifecycle->resume($subscriptionId);
    }

    protected function successLabel(): string
    {
        return (string) __('Resumed');
    }
}
