<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Model;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionRepositoryInterface;
use Gtstudio\Ebizcharge\Model\SubscriptionSchedule;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class SubscriptionScheduleTest extends TestCase
{
    private SubscriptionSchedule $schedule;

    protected function setUp(): void
    {
        $this->schedule = new SubscriptionSchedule(
            $this->createMock(SubscriptionRepositoryInterface::class)
        );
    }

    public static function frequencyProvider(): array
    {
        return [
            'daily' => [SubscriptionInterface::FREQUENCY_DAILY, '2026-01-15', '2026-01-16'],
            'weekly' => [SubscriptionInterface::FREQUENCY_WEEKLY, '2026-01-15', '2026-01-22'],
            'bi-weekly' => [SubscriptionInterface::FREQUENCY_BIWEEKLY, '2026-01-15', '2026-01-29'],
            'monthly' => [SubscriptionInterface::FREQUENCY_MONTHLY, '2026-01-15', '2026-02-15'],
            'bi-monthly' => [SubscriptionInterface::FREQUENCY_BIMONTHLY, '2026-01-15', '2026-03-15'],
            'quarterly' => [SubscriptionInterface::FREQUENCY_QUARTERLY, '2026-01-15', '2026-04-15'],
            'bi-annually' => [SubscriptionInterface::FREQUENCY_BIANNUALLY, '2026-01-15', '2026-07-15'],
            'annually' => [SubscriptionInterface::FREQUENCY_ANNUALLY, '2026-01-15', '2027-01-15'],
        ];
    }

    /**
     * @dataProvider frequencyProvider
     */
    public function testComputeNextBillDate(string $frequency, string $from, string $expected): void
    {
        $next = $this->schedule->computeNextBillDate(
            $frequency,
            new \DateTimeImmutable($from)
        );
        $this->assertSame($expected, $next->format('Y-m-d'));
    }

    public function testMonthRolloverClampsToEndOfFebruary(): void
    {
        $next = $this->schedule->computeNextBillDate(
            SubscriptionInterface::FREQUENCY_MONTHLY,
            new \DateTimeImmutable('2026-01-31')
        );
        $this->assertSame('2026-02-28', $next->format('Y-m-d'));
    }

    public function testLeapYearFebruaryHandledCorrectly(): void
    {
        $next = $this->schedule->computeNextBillDate(
            SubscriptionInterface::FREQUENCY_MONTHLY,
            new \DateTimeImmutable('2024-01-31')
        );
        $this->assertSame('2024-02-29', $next->format('Y-m-d'));
    }

    public function testUnknownFrequencyThrows(): void
    {
        $this->expectException(LocalizedException::class);
        $this->schedule->computeNextBillDate(
            'fortnightly_on_a_blue_moon',
            new \DateTimeImmutable('2026-01-15')
        );
    }

    public function testAcceptsMutableDateTime(): void
    {
        $from = new \DateTime('2026-01-15');
        $next = $this->schedule->computeNextBillDate(
            SubscriptionInterface::FREQUENCY_WEEKLY,
            $from
        );
        $this->assertSame('2026-01-22', $next->format('Y-m-d'));
        // Original mutable date must not be mutated
        $this->assertSame('2026-01-15', $from->format('Y-m-d'));
    }

    public function testAdvanceRestoresOriginalEndOfMonthAnchor(): void
    {
        $subscription = $this->createMock(SubscriptionInterface::class);
        $subscription->method('getFrequency')->willReturn(SubscriptionInterface::FREQUENCY_MONTHLY);
        $subscription->method('getNextBillDate')->willReturn('2026-02-28');
        $subscription->method('getStartDate')->willReturn('2026-01-31');
        $subscription->method('getCompletedCycles')->willReturn(1);
        $subscription->method('getMaxCycles')->willReturn(null);
        $subscription->method('getEndDate')->willReturn(null);
        $subscription->expects($this->once())->method('setNextBillDate')->with('2026-03-31');
        $subscription->expects($this->once())->method('setCompletedCycles')->with(2);

        $repository = $this->createMock(SubscriptionRepositoryInterface::class);
        $repository->expects($this->once())->method('save')->with($subscription)->willReturn($subscription);
        (new SubscriptionSchedule($repository))->advanceSubscription($subscription);
    }
}
