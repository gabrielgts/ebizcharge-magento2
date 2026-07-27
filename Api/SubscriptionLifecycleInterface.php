<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Api;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/** @api */
interface SubscriptionLifecycleInterface
{
    /** Pauses an active subscription. */
    public function pause(int $subscriptionId, ?string $reason = null): SubscriptionInterface;

    /** Resumes a paused subscription. */
    public function resume(int $subscriptionId): SubscriptionInterface;

    /** Cancels a subscription permanently. */
    public function cancel(int $subscriptionId, ?string $reason = null): SubscriptionInterface;

    /** Queues the next charge immediately. */
    public function chargeNow(int $subscriptionId): int;

    /** Replaces the Vault token used for future charges. */
    public function updatePaymentMethod(int $subscriptionId, int $vaultPaymentTokenId): SubscriptionInterface;
}
