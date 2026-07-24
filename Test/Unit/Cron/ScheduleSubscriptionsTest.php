<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Cron;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionChargeInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionChargeRepositoryInterface;
use Gtstudio\Ebizcharge\Cron\ScheduleSubscriptions;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Model\ResourceModel\Subscription\CollectionFactory as SubscriptionCollectionFactory;
use Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionCharge\Collection as ChargeCollection;
use Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionCharge\CollectionFactory as ChargeCollectionFactory;
use Gtstudio\Ebizcharge\Model\SubscriptionChargeFactory;
use Gtstudio\Ebizcharge\Service\CorrelationIdProvider;
use PHPUnit\Framework\TestCase;

class ScheduleSubscriptionsTest extends TestCase
{
    public function testOpenAttemptPreventsAnotherChargeFromBeingScheduled(): void
    {
        $open = $this->collection(1, []);

        $this->assertNull($this->nextAttempt([$open]));
    }

    public function testTerminalAttemptsProduceNextImmutableAttemptNumber(): void
    {
        $failed = $this->charge(SubscriptionChargeInterface::STATUS_FAILED, 2);
        $skipped = $this->charge(SubscriptionChargeInterface::STATUS_SKIPPED, 3);
        $open = $this->collection(0, []);
        $cycle = $this->collection(2, [$failed, $skipped]);

        $this->assertSame(4, $this->nextAttempt([$open, $cycle]));
    }

    /** @param ChargeCollection[] $collections */
    private function nextAttempt(array $collections): ?int
    {
        $factory = $this->createMock(ChargeCollectionFactory::class);
        $factory->method('create')->willReturnOnConsecutiveCalls(...$collections);
        $scheduler = new ScheduleSubscriptions(
            $this->createMock(SubscriptionCollectionFactory::class),
            $factory,
            $this->createMock(SubscriptionChargeFactory::class),
            $this->createMock(SubscriptionChargeRepositoryInterface::class),
            $this->createMock(CorrelationIdProvider::class),
            $this->createMock(Logger::class)
        );

        $method = new \ReflectionMethod($scheduler, 'getNextAttempt');
        return $method->invoke($scheduler, 12, '2026-08-31 00:00:00');
    }

    /** @param SubscriptionChargeInterface[] $items */
    private function collection(int $size, array $items): ChargeCollection
    {
        $collection = $this->getMockBuilder(ChargeCollection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addFieldToFilter', 'setPageSize', 'getSize', 'getIterator'])
            ->getMock();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getSize')->willReturn($size);
        $collection->method('getIterator')->willReturn(new \ArrayIterator($items));
        return $collection;
    }

    private function charge(string $status, int $attempt): SubscriptionChargeInterface
    {
        $charge = $this->createMock(SubscriptionChargeInterface::class);
        $charge->method('getStatus')->willReturn($status);
        $charge->method('getAttemptCount')->willReturn($attempt);
        return $charge;
    }
}
