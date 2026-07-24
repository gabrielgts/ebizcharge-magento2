<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Api;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;

/**
 * Computes when the next charge runs. Pure date math; no IO.
 *
 * @api
 */
interface SubscriptionScheduleInterface
{
    /**
     * Compute the next bill date for a given frequency, starting from $from.
     */
    public function computeNextBillDate(string $frequency, \DateTimeInterface $from): \DateTimeInterface;

    /**
     * Advance a subscription's next_bill_date by one cycle and increment completed_cycles.
     * Marks completed if max_cycles reached. Marks expired if past end_date. Saves the row.
     */
    public function advanceSubscription(SubscriptionInterface $subscription): SubscriptionInterface;
}
