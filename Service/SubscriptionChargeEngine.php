<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionChargeInterface;
use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionChargeEngineInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionChargeRepositoryInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionRepositoryInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionScheduleInterface;
use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Model\SubscriptionChargeFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;

/** Processes one pending subscription charge. */
class SubscriptionChargeEngine implements SubscriptionChargeEngineInterface
{
    public function __construct(
        private readonly SubscriptionChargeRepositoryInterface $chargeRepository,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly SubscriptionScheduleInterface $schedule,
        private readonly SyntheticOrderBuilder $orderBuilder,
        private readonly SubscriptionChargeFactory $chargeFactory,
        private readonly SubscriptionEmailNotifier $notifier,
        private readonly ResourceConnection $resource,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CorrelationIdProvider $correlationId,
        private readonly Config $config,
        private readonly Logger $logger,
    ) {
    }

    public function chargeOne(int $chargeId): bool
    {
        $connection = $this->resource->getConnection();

        try {
            $charge = $this->chargeRepository->getById($chargeId);
        } catch (NoSuchEntityException) {
            return false;
        }

        if ($charge->getStatus() !== SubscriptionChargeInterface::STATUS_PENDING) {
            return false;
        }

        // Acquire — flip status from pending to in_progress as the lock signal
        $connection->beginTransaction();
        try {
            $rowsAffected = $connection->update(
                $this->resource->getTableName('gtstudio_ebizcharge_subscription_charge'),
                [
                    SubscriptionChargeInterface::STATUS => SubscriptionChargeInterface::STATUS_IN_PROGRESS,
                    SubscriptionChargeInterface::ATTEMPTED_AT => date('Y-m-d H:i:s'),
                ],
                [
                    'entity_id = ?' => $chargeId,
                    'status = ?' => SubscriptionChargeInterface::STATUS_PENDING,
                ]
            );
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->logger->error('subscription.charge.lock_failed', [
                'charge_id' => $chargeId,
                'message' => $e->getMessage(),
            ]);
            return false;
        }

        if ($rowsAffected === 0) {
            // Another worker grabbed it
            return false;
        }

        $charge = $this->chargeRepository->getById($chargeId);
        $subscription = $this->subscriptionRepository->getById($charge->getSubscriptionId());
        if (!in_array($subscription->getStatus(), [
            SubscriptionInterface::STATUS_ACTIVE,
            SubscriptionInterface::STATUS_FAILING,
        ], true)) {
            $charge->setStatus(SubscriptionChargeInterface::STATUS_SKIPPED);
            $this->chargeRepository->save($charge);
            return false;
        }

        try {
            $orderId = $this->orderBuilder->placeOrder($subscription, $charge->getCorrelationId());
            $this->onSuccess($charge, $subscription, $orderId);
            return true;
        } catch (\Throwable $exception) {
            $this->onFailure($charge, $subscription, $exception);
            return false;
        }
    }

    private function onSuccess(
        SubscriptionChargeInterface $charge,
        SubscriptionInterface $subscription,
        int $orderId
    ): void {
        $charge->setStatus(SubscriptionChargeInterface::STATUS_SUCCEEDED);
        $charge->setMagentoOrderId($orderId);
        try {
            $order = $this->orderRepository->get($orderId);
            $refNum = trim((string) ($order->getPayment()?->getLastTransId() ?? ''));
            if ($refNum !== '') {
                $charge->setGatewayRefNum($refNum);
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('subscription.charge.reference_lookup_failed', [
                'charge_id' => $charge->getEntityId(),
                'order_id' => $orderId,
                'reason' => $exception::class,
            ]);
        }
        $this->chargeRepository->save($charge);

        $this->schedule->advanceSubscription($subscription);

        $this->logger->info('subscription.charge.succeeded', [
            'charge_id' => $charge->getEntityId(),
            'subscription_id' => $subscription->getEntityId(),
            'order_id' => $orderId,
            'next_bill_date' => $subscription->getNextBillDate(),
        ]);
    }

    private function onFailure(
        SubscriptionChargeInterface $charge,
        SubscriptionInterface $subscription,
        \Throwable $exception
    ): void {
        $charge->setStatus(SubscriptionChargeInterface::STATUS_FAILED);
        $charge->setErrorMessage(substr($this->safeFailureMessage($exception), 0, 4096));
        $this->chargeRepository->save($charge);

        $subscription->setFailureCount($subscription->getFailureCount() + 1);
        $failureThresholdFailing = $this->config->getSubscriptionFailureThresholdFailing(
            $subscription->getStoreId()
        );
        $failureThresholdCancel = $this->config->getSubscriptionFailureThresholdCancel(
            $subscription->getStoreId()
        );

        if ($subscription->getFailureCount() >= $failureThresholdCancel) {
            $subscription->setStatus(SubscriptionInterface::STATUS_CANCELLED);
        } elseif ($subscription->getFailureCount() >= $failureThresholdFailing) {
            $subscription->setStatus(SubscriptionInterface::STATUS_FAILING);
        }

        $this->subscriptionRepository->save($subscription);

        $this->logger->warning('subscription.charge.failed', [
            'charge_id' => $charge->getEntityId(),
            'subscription_id' => $subscription->getEntityId(),
            'failure_count' => $subscription->getFailureCount(),
            'subscription_status' => $subscription->getStatus(),
            'error' => $exception->getMessage(),
        ]);

        // Customer notification — best-effort, never let a notification failure mask a charge failure
        try {
            $this->notifier->notifyChargeFailed($subscription, $charge);
        } catch (\Throwable $emailError) {
            $this->logger->warning('subscription.charge.failed.notification_send_error', [
                'charge_id' => $charge->getEntityId(),
                'message' => $emailError->getMessage(),
            ]);
        }

        if ($subscription->getStatus() !== SubscriptionInterface::STATUS_CANCELLED) {
            $this->queueRetry($charge, $subscription);
        }
    }

    private function queueRetry(
        SubscriptionChargeInterface $failedCharge,
        SubscriptionInterface $subscription
    ): void {
        try {
            $retry = $this->chargeFactory->create();
            $retry->setSubscriptionId((int) $subscription->getEntityId());
            $retry->setScheduledFor($failedCharge->getScheduledFor());
            $retry->setStatus(SubscriptionChargeInterface::STATUS_PENDING);
            $retry->setAttemptCount($failedCharge->getAttemptCount() + 1);
            $this->correlationId->reset();
            $retry->setCorrelationId($this->correlationId->get());
            $this->chargeRepository->save($retry);
            $this->logger->info('subscription.charge.retry_queued', [
                'subscription_id' => $subscription->getEntityId(),
                'failed_charge_id' => $failedCharge->getEntityId(),
                'retry_charge_id' => $retry->getEntityId(),
                'attempt_count' => $retry->getAttemptCount(),
            ]);
        } catch (\Throwable $e) {
            // A duplicate means another worker already queued the same immutable attempt.
            $this->logger->warning('subscription.charge.retry_queue_failed', [
                'subscription_id' => $subscription->getEntityId(),
                'failed_charge_id' => $failedCharge->getEntityId(),
                'reason' => $e::class,
            ]);
        }
    }

    private function safeFailureMessage(\Throwable $error): string
    {
        if ($error instanceof LocalizedException) {
            return $error->getMessage();
        }
        return (string) __('The scheduled subscription charge could not be processed.');
    }
}
