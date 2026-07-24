<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionChargeInterface;
use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionChargeRepositoryInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionLifecycleInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionRepositoryInterface;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Service\CorrelationIdProvider;
use Gtstudio\Ebizcharge\Service\RecurringVaultTokenValidator;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;

class SubscriptionLifecycle implements SubscriptionLifecycleInterface
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly SubscriptionChargeRepositoryInterface $chargeRepository,
        private readonly SubscriptionChargeFactory $chargeFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly RecurringVaultTokenValidator $tokenValidator,
        private readonly CorrelationIdProvider $correlationId,
        private readonly Logger $logger
    ) {
    }

    public function pause(int $subscriptionId, ?string $reason = null): SubscriptionInterface
    {
        $subscription = $this->subscriptionRepository->getById($subscriptionId);

        if ($subscription->getStatus() === SubscriptionInterface::STATUS_CANCELLED
            || $subscription->getStatus() === SubscriptionInterface::STATUS_COMPLETED
            || $subscription->getStatus() === SubscriptionInterface::STATUS_EXPIRED) {
            throw new LocalizedException(__('Cannot pause a %1 subscription.', $subscription->getStatus()));
        }

        $subscription->setStatus(SubscriptionInterface::STATUS_PAUSED);
        $this->subscriptionRepository->save($subscription);
        $this->skipPendingCharges($subscriptionId);

        $this->logger->info('subscription.paused', [
            'subscription_id' => $subscriptionId,
            'reason' => $reason,
        ]);

        return $subscription;
    }

    public function resume(int $subscriptionId): SubscriptionInterface
    {
        $subscription = $this->subscriptionRepository->getById($subscriptionId);

        if ($subscription->getStatus() !== SubscriptionInterface::STATUS_PAUSED
            && $subscription->getStatus() !== SubscriptionInterface::STATUS_FAILING) {
            throw new LocalizedException(__('Only paused or failing subscriptions can be resumed.'));
        }

        $subscription->setStatus(SubscriptionInterface::STATUS_ACTIVE);
        $subscription->setFailureCount(0);

        // Recompute next_bill_date from today if it's in the past so the next cron picks it up.
        if ($subscription->getNextBillDate() < date('Y-m-d')) {
            $subscription->setNextBillDate(date('Y-m-d'));
        }

        $this->subscriptionRepository->save($subscription);

        $this->logger->info('subscription.resumed', ['subscription_id' => $subscriptionId]);

        return $subscription;
    }

    public function cancel(int $subscriptionId, ?string $reason = null): SubscriptionInterface
    {
        $subscription = $this->subscriptionRepository->getById($subscriptionId);

        if ($subscription->getStatus() === SubscriptionInterface::STATUS_CANCELLED) {
            return $subscription;
        }

        $subscription->setStatus(SubscriptionInterface::STATUS_CANCELLED);
        $this->subscriptionRepository->save($subscription);

        $this->skipPendingCharges($subscriptionId);

        $this->logger->info('subscription.cancelled', [
            'subscription_id' => $subscriptionId,
            'reason' => $reason,
        ]);

        return $subscription;
    }

    private function skipPendingCharges(int $subscriptionId): void
    {
        $criteria = $this->searchCriteriaBuilder
            ->addFilter(SubscriptionChargeInterface::SUBSCRIPTION_ID, $subscriptionId)
            ->addFilter(SubscriptionChargeInterface::STATUS, SubscriptionChargeInterface::STATUS_PENDING)
            ->create();

        foreach ($this->chargeRepository->getList($criteria)->getItems() as $charge) {
            $charge->setStatus(SubscriptionChargeInterface::STATUS_SKIPPED);
            $this->chargeRepository->save($charge);
        }
    }

    public function chargeNow(int $subscriptionId): int
    {
        $subscription = $this->subscriptionRepository->getById($subscriptionId);

        if ($subscription->getStatus() !== SubscriptionInterface::STATUS_ACTIVE) {
            throw new LocalizedException(__('Only an active subscription can be charged now.'));
        }
        if ($this->hasOpenCharge($subscriptionId)) {
            throw new LocalizedException(__('A charge is already queued for this subscription.'));
        }
        $this->tokenValidator->validate($subscription);

        $charge = $this->chargeFactory->create();
        $charge->setSubscriptionId($subscriptionId);
        $charge->setScheduledFor(date('Y-m-d H:i:s'));
        $charge->setStatus(SubscriptionChargeInterface::STATUS_PENDING);
        $charge->setAttemptCount(1);
        $charge->setCorrelationId($this->correlationId->get());
        $this->chargeRepository->save($charge);

        $this->logger->info('subscription.charge_now_queued', [
            'subscription_id' => $subscriptionId,
            'charge_id' => $charge->getEntityId(),
        ]);

        return (int) $charge->getEntityId();
    }

    private function hasOpenCharge(int $subscriptionId): bool
    {
        foreach ([
            SubscriptionChargeInterface::STATUS_PENDING,
            SubscriptionChargeInterface::STATUS_IN_PROGRESS,
        ] as $status) {
            $criteria = $this->searchCriteriaBuilder
                ->addFilter(SubscriptionChargeInterface::SUBSCRIPTION_ID, $subscriptionId)
                ->addFilter(SubscriptionChargeInterface::STATUS, $status)
                ->setPageSize(1)
                ->create();
            if ($this->chargeRepository->getList($criteria)->getTotalCount() > 0) {
                return true;
            }
        }
        return false;
    }

    public function updatePaymentMethod(int $subscriptionId, int $vaultPaymentTokenId): SubscriptionInterface
    {
        $subscription = $this->subscriptionRepository->getById($subscriptionId);
        $this->tokenValidator->validate($subscription, $vaultPaymentTokenId);

        $subscription->setVaultPaymentTokenId($vaultPaymentTokenId);
        // If we were in failing state because of a card problem, give it another chance.
        if ($subscription->getStatus() === SubscriptionInterface::STATUS_FAILING) {
            $subscription->setStatus(SubscriptionInterface::STATUS_ACTIVE);
            $subscription->setFailureCount(0);
        }

        $this->subscriptionRepository->save($subscription);

        $this->logger->info('subscription.payment_method_updated', [
            'subscription_id' => $subscriptionId,
            'vault_token_id' => $vaultPaymentTokenId,
        ]);

        return $subscription;
    }
}
