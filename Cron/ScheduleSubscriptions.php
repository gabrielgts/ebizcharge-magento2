<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Cron;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionChargeInterface;
use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionChargeRepositoryInterface;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Model\ResourceModel\Subscription\Collection as SubscriptionCollection;
use Gtstudio\Ebizcharge\Model\ResourceModel\Subscription\CollectionFactory as SubscriptionCollectionFactory;
use Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionCharge\Collection as ChargeCollection;
use Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionCharge\CollectionFactory as ChargeCollectionFactory;
use Gtstudio\Ebizcharge\Model\SubscriptionChargeFactory;
use Gtstudio\Ebizcharge\Service\CorrelationIdProvider;

/** Queues due subscription billing cycles. */
class ScheduleSubscriptions
{
    public function __construct(
        private readonly SubscriptionCollectionFactory $subscriptionCollectionFactory,
        private readonly ChargeCollectionFactory $chargeCollectionFactory,
        private readonly SubscriptionChargeFactory $chargeFactory,
        private readonly SubscriptionChargeRepositoryInterface $chargeRepository,
        private readonly CorrelationIdProvider $correlationId,
        private readonly Logger $logger
    ) {
    }

    public function execute(): void
    {
        /** @var SubscriptionCollection $collection */
        $collection = $this->subscriptionCollectionFactory->create();
        $collection->addFieldToFilter(SubscriptionInterface::STATUS, ['in' => [
                SubscriptionInterface::STATUS_ACTIVE,
                SubscriptionInterface::STATUS_FAILING,
            ]])
            ->addFieldToFilter(SubscriptionInterface::NEXT_BILL_DATE, ['lteq' => date('Y-m-d')]);

        $scheduled = 0;
        foreach ($collection as $subscription) {
            try {
                $subscriptionId = (int) $subscription->getEntityId();
                $scheduledFor = $subscription->getNextBillDate() . ' 00:00:00';
                $attempt = $this->getNextAttempt($subscriptionId, $scheduledFor);
                if ($attempt === null) {
                    continue;
                }
                $charge = $this->chargeFactory->create();
                $charge->setSubscriptionId($subscriptionId);
                $charge->setScheduledFor($scheduledFor);
                $charge->setStatus(SubscriptionChargeInterface::STATUS_PENDING);
                $charge->setAttemptCount($attempt);
                $this->correlationId->reset();
                $charge->setCorrelationId($this->correlationId->get());
                $this->chargeRepository->save($charge);
                $scheduled++;
            } catch (\Throwable $e) {
                // Most likely the unique index — already scheduled. Safe to skip.
                $this->logger->info('subscription.schedule.skip', [
                    'subscription_id' => $subscription->getEntityId(),
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('subscription.schedule.summary', [
            'candidates' => $collection->getSize(),
            'scheduled' => $scheduled,
        ]);
    }

    /** Returns the next attempt number when the cycle is eligible. */
    private function getNextAttempt(int $subscriptionId, string $scheduledFor): ?int
    {
        /** @var ChargeCollection $openCharges */
        $openCharges = $this->chargeCollectionFactory->create();
        $openCharges->addFieldToFilter(SubscriptionChargeInterface::SUBSCRIPTION_ID, $subscriptionId)
            ->addFieldToFilter(SubscriptionChargeInterface::STATUS, ['in' => [
                SubscriptionChargeInterface::STATUS_PENDING,
                SubscriptionChargeInterface::STATUS_IN_PROGRESS,
            ]])
            ->setPageSize(1);
        if ($openCharges->getSize() > 0) {
            return null;
        }

        /** @var ChargeCollection $cycleCharges */
        $cycleCharges = $this->chargeCollectionFactory->create();
        $cycleCharges->addFieldToFilter(SubscriptionChargeInterface::SUBSCRIPTION_ID, $subscriptionId)
            ->addFieldToFilter(SubscriptionChargeInterface::SCHEDULED_FOR, $scheduledFor);

        $maxAttempt = 0;
        foreach ($cycleCharges as $charge) {
            if ($charge->getStatus() === SubscriptionChargeInterface::STATUS_SUCCEEDED) {
                return null;
            }
            $maxAttempt = max($maxAttempt, $charge->getAttemptCount());
        }

        return $maxAttempt + 1;
    }
}
