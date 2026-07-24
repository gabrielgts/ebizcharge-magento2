<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Api;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Subscription lifecycle operations.
 *
 * Public extension point for other modules (BabyLock_SewedMemberSubscription, fraud detection,
 * etc.) — when those modules are ready to retarget, they wire onto this interface instead of
 * patching internal classes.
 *
 * @api
 */
interface SubscriptionLifecycleInterface
{
    /**
     * Pause an active subscription. No future charges generated until resumed.
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function pause(int $subscriptionId, ?string $reason = null): SubscriptionInterface;

    /**
     * Resume a paused subscription. Recomputes next_bill_date from today.
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function resume(int $subscriptionId): SubscriptionInterface;

    /**
     * Cancel permanently. Pending charges become "skipped"; no future charges generated.
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function cancel(int $subscriptionId, ?string $reason = null): SubscriptionInterface;

    /**
     * Force the next charge to run now (admin "Charge Now" button or customer manual retry).
     * Returns the charge entity_id that will be processed by the next charging cron tick.
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function chargeNow(int $subscriptionId): int;

    /**
     * Replace the vault token used for future charges. Used by customer "Update payment method"
     * and admin equivalent.
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function updatePaymentMethod(int $subscriptionId, int $vaultPaymentTokenId): SubscriptionInterface;
}
