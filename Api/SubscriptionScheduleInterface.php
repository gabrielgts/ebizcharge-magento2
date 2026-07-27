<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Api;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;

/** @api */
interface SubscriptionScheduleInterface
{
    /** Computes the next bill date. */
    public function computeNextBillDate(string $frequency, \DateTimeInterface $from): \DateTimeInterface;

    /** Advances and persists one subscription billing cycle. */
    public function advanceSubscription(SubscriptionInterface $subscription): SubscriptionInterface;
}
