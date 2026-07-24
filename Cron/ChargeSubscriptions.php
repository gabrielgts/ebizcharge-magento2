<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Cron;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionChargeInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionChargeEngineInterface;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionCharge\Collection as ChargeCollection;
use Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionCharge\CollectionFactory as ChargeCollectionFactory;

/**
 * Every 15 minutes — picks pending charges and runs them through the engine.
 *
 * Each call processes up to a configurable batch size. On a heavy day with many subscriptions,
 * subsequent ticks pick up the rest. The charge engine handles per-row locking.
 */
class ChargeSubscriptions
{
    private const BATCH_SIZE = 50;

    public function __construct(
        private readonly ChargeCollectionFactory $chargeCollectionFactory,
        private readonly SubscriptionChargeEngineInterface $chargeEngine,
        private readonly Logger $logger
    ) {
    }

    public function execute(): void
    {
        /** @var ChargeCollection $collection */
        $collection = $this->chargeCollectionFactory->create();
        $collection->addFieldToFilter(SubscriptionChargeInterface::STATUS, SubscriptionChargeInterface::STATUS_PENDING)
            ->addFieldToFilter(SubscriptionChargeInterface::SCHEDULED_FOR, ['lteq' => date('Y-m-d H:i:s')])
            ->setOrder(SubscriptionChargeInterface::SCHEDULED_FOR, 'ASC')
            ->setPageSize(self::BATCH_SIZE);

        $succeeded = 0;
        $failed = 0;
        foreach ($collection as $charge) {
            try {
                if ($this->chargeEngine->chargeOne((int) $charge->getEntityId())) {
                    $succeeded++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->logger->error('subscription.charge.cron_exception', [
                    'charge_id' => $charge->getEntityId(),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('subscription.charge.summary', [
            'attempted' => $succeeded + $failed,
            'succeeded' => $succeeded,
            'failed' => $failed,
        ]);
    }
}
