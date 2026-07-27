<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model;

use DateTimeImmutable;
use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionRepositoryInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionScheduleInterface;
use Magento\Framework\Exception\LocalizedException;

class SubscriptionSchedule implements SubscriptionScheduleInterface
{
    /** Maps supported frequencies to date intervals. */
    private const FREQUENCY_INTERVALS = [
        SubscriptionInterface::FREQUENCY_DAILY => '+1 day',
        SubscriptionInterface::FREQUENCY_WEEKLY => '+1 week',
        SubscriptionInterface::FREQUENCY_BIWEEKLY => '+2 weeks',
        SubscriptionInterface::FREQUENCY_MONTHLY => '+1 month',
        SubscriptionInterface::FREQUENCY_BIMONTHLY => '+2 months',
        SubscriptionInterface::FREQUENCY_QUARTERLY => '+3 months',
        SubscriptionInterface::FREQUENCY_BIANNUALLY => '+6 months',
        SubscriptionInterface::FREQUENCY_ANNUALLY => '+1 year',
    ];

    private const FREQUENCY_MONTHS = [
        SubscriptionInterface::FREQUENCY_MONTHLY => 1,
        SubscriptionInterface::FREQUENCY_BIMONTHLY => 2,
        SubscriptionInterface::FREQUENCY_QUARTERLY => 3,
        SubscriptionInterface::FREQUENCY_BIANNUALLY => 6,
        SubscriptionInterface::FREQUENCY_ANNUALLY => 12,
    ];

    public function __construct(private readonly SubscriptionRepositoryInterface $subscriptionRepository)
    {
    }

    public function computeNextBillDate(string $frequency, \DateTimeInterface $from): \DateTimeInterface
    {
        if (!isset(self::FREQUENCY_INTERVALS[$frequency])) {
            throw new LocalizedException(__('Unknown subscription frequency: %1', $frequency));
        }

        $immutable = $from instanceof \DateTimeImmutable
            ? $from
            : \DateTimeImmutable::createFromInterface($from);

        if (isset(self::FREQUENCY_MONTHS[$frequency])) {
            return $this->addMonthsClamped(
                $immutable,
                self::FREQUENCY_MONTHS[$frequency],
                (int) $immutable->format('j')
            );
        }

        return $immutable->modify(self::FREQUENCY_INTERVALS[$frequency]);
    }

    public function advanceSubscription(SubscriptionInterface $subscription): SubscriptionInterface
    {
        $frequency = $subscription->getFrequency();
        $currentBillDate = new DateTimeImmutable($subscription->getNextBillDate());
        if (isset(self::FREQUENCY_MONTHS[$frequency])) {
            // Preserve the original billing-day anchor across short months.
            $anchorDay = (int) (new DateTimeImmutable($subscription->getStartDate()))->format('j');
            $nextDate = $this->addMonthsClamped(
                $currentBillDate,
                self::FREQUENCY_MONTHS[$frequency],
                $anchorDay
            );
        } else {
            $nextDate = $this->computeNextBillDate($frequency, $currentBillDate);
        }
        $subscription->setNextBillDate($nextDate->format('Y-m-d'));
        $subscription->setCompletedCycles($subscription->getCompletedCycles() + 1);
        $subscription->setLastChargedAt(date('Y-m-d H:i:s'));
        $subscription->setFailureCount(0);

        $maxCycles = $subscription->getMaxCycles();
        if ($maxCycles !== null && $subscription->getCompletedCycles() >= $maxCycles) {
            $subscription->setStatus(SubscriptionInterface::STATUS_COMPLETED);
        }

        $endDate = $subscription->getEndDate();
        if ($endDate !== null && $nextDate->format('Y-m-d') > $endDate) {
            $subscription->setStatus(SubscriptionInterface::STATUS_EXPIRED);
        }

        return $this->subscriptionRepository->save($subscription);
    }

    private function addMonthsClamped(\DateTimeImmutable $from, int $months, int $anchorDay): \DateTimeImmutable
    {
        $targetMonth = $from->modify('first day of this month')->modify('+' . $months . ' months');
        $day = min($anchorDay, (int) $targetMonth->format('t'));
        return $targetMonth->setDate(
            (int) $targetMonth->format('Y'),
            (int) $targetMonth->format('n'),
            $day
        );
    }
}
