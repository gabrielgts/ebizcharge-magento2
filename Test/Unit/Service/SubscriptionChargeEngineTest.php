<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Service;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionChargeInterface;
use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionChargeRepositoryInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionRepositoryInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionScheduleInterface;
use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Model\SubscriptionChargeFactory;
use Gtstudio\Ebizcharge\Service\CorrelationIdProvider;
use Gtstudio\Ebizcharge\Service\SubscriptionChargeEngine;
use Gtstudio\Ebizcharge\Service\SubscriptionEmailNotifier;
use Gtstudio\Ebizcharge\Service\SyntheticOrderBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\TestCase;

class SubscriptionChargeEngineTest extends TestCase
{
    public function testFailureKeepsAttemptImmutableAndQueuesNextAttempt(): void
    {
        $failedCharge = $this->createMock(SubscriptionChargeInterface::class);
        $failedCharge->method('getEntityId')->willReturn(10);
        $failedCharge->method('getSubscriptionId')->willReturn(20);
        $failedCharge->method('getStatus')->willReturn(SubscriptionChargeInterface::STATUS_PENDING);
        $failedCharge->method('getAttemptCount')->willReturn(1);
        $failedCharge->method('getScheduledFor')->willReturn('2026-08-01 00:00:00');
        $failedCharge->method('getCorrelationId')->willReturn('gtsbz-first');
        $failedCharge->expects($this->never())->method('setAttemptCount');
        $failedCharge->expects($this->once())->method('setStatus')
            ->with(SubscriptionChargeInterface::STATUS_FAILED);

        $failureCount = 0;
        $status = SubscriptionInterface::STATUS_ACTIVE;
        $subscription = $this->createMock(SubscriptionInterface::class);
        $subscription->method('getEntityId')->willReturn(20);
        $subscription->method('getStatus')->willReturnCallback(
            static function () use (&$status): string {
                return $status;
            }
        );
        $subscription->method('setStatus')->willReturnCallback(
            static function (string $newStatus) use (&$status, $subscription): SubscriptionInterface {
                $status = $newStatus;
                return $subscription;
            }
        );
        $subscription->method('getFailureCount')->willReturnCallback(
            static function () use (&$failureCount): int {
                return $failureCount;
            }
        );
        $subscription->method('setFailureCount')->willReturnCallback(
            static function (int $count) use (&$failureCount, $subscription): SubscriptionInterface {
                $failureCount = $count;
                return $subscription;
            }
        );
        $subscription->method('getStoreId')->willReturn(1);

        $retryAttempt = 0;
        $retry = $this->createMock(SubscriptionChargeInterface::class);
        $retry->expects($this->once())->method('setScheduledFor')->with('2026-08-01 00:00:00');
        $retry->method('setAttemptCount')->willReturnCallback(
            static function (int $count) use (&$retryAttempt, $retry): SubscriptionChargeInterface {
                $retryAttempt = $count;
                return $retry;
            }
        );
        $retry->method('getAttemptCount')->willReturnCallback(static fn (): int => $retryAttempt);

        $chargeRepository = $this->createMock(SubscriptionChargeRepositoryInterface::class);
        $chargeRepository->method('getById')->with(10)->willReturn($failedCharge);
        $saved = [];
        $chargeRepository->method('save')->willReturnCallback(
            static function (SubscriptionChargeInterface $charge) use (&$saved): SubscriptionChargeInterface {
                $saved[] = $charge;
                return $charge;
            }
        );

        $subscriptionRepository = $this->createMock(SubscriptionRepositoryInterface::class);
        $subscriptionRepository->method('getById')->with(20)->willReturn($subscription);
        $subscriptionRepository->method('save')->willReturn($subscription);

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('update')->willReturn(1);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturn('gtstudio_ebizcharge_subscription_charge');

        $orderBuilder = $this->createMock(SyntheticOrderBuilder::class);
        $orderBuilder->method('placeOrder')->willThrowException(new \RuntimeException('declined'));
        $factory = $this->createMock(SubscriptionChargeFactory::class);
        $factory->method('create')->willReturn($retry);
        $config = $this->createMock(Config::class);
        $config->method('getSubscriptionFailureThresholdFailing')->willReturn(3);
        $config->method('getSubscriptionFailureThresholdCancel')->willReturn(5);
        $correlation = $this->createMock(CorrelationIdProvider::class);
        $correlation->method('get')->willReturn('gtsbz-retry');

        $engine = new SubscriptionChargeEngine(
            $chargeRepository,
            $subscriptionRepository,
            $this->createMock(SubscriptionScheduleInterface::class),
            $orderBuilder,
            $factory,
            $this->createMock(SubscriptionEmailNotifier::class),
            $resource,
            $this->createMock(OrderRepositoryInterface::class),
            $correlation,
            $config,
            $this->createMock(Logger::class)
        );

        $this->assertFalse($engine->chargeOne(10));
        $this->assertSame(1, $failureCount);
        $this->assertSame(2, $retryAttempt);
        $this->assertContains($failedCharge, $saved);
        $this->assertContains($retry, $saved);
    }
}
