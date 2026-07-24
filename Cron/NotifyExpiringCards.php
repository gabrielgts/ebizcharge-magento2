<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Cron;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Model\ResourceModel\Subscription\Collection as SubscriptionCollection;
use Gtstudio\Ebizcharge\Model\ResourceModel\Subscription\CollectionFactory as SubscriptionCollectionFactory;
use Gtstudio\Ebizcharge\Service\SubscriptionEmailNotifier;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

/**
 * Daily — for active subscriptions whose attached vault token expires in exactly N days, send a
 * "card expiring" email (default thresholds: 30 / 7 / 1 days, admin-configurable as comma list).
 *
 * "Exactly N days" instead of a range avoids re-sending. Multi-threshold means the customer gets
 * one nudge a month out, one a week out, and one the day before — three escalating reminders.
 */
class NotifyExpiringCards
{
    public const CONFIG_DAYS_THRESHOLDS = 'payment/gtstudio_ebizcharge/subscription_card_expiring_email_days';
    private const DEFAULT_THRESHOLDS = '30,7,1';

    public function __construct(
        private readonly SubscriptionCollectionFactory $subscriptionCollectionFactory,
        private readonly PaymentTokenRepositoryInterface $tokenRepository,
        private readonly SubscriptionEmailNotifier $notifier,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Logger $logger
    ) {
    }

    public function execute(): void
    {
        $thresholds = $this->loadThresholds();
        $sent = 0;

        foreach ($thresholds as $days) {
            $targetDate = date('Y-m-d', strtotime("+{$days} days"));

            /** @var SubscriptionCollection $collection */
            $collection = $this->subscriptionCollectionFactory->create();
            $collection->addFieldToFilter(SubscriptionInterface::STATUS, SubscriptionInterface::STATUS_ACTIVE)
                ->addFieldToFilter(SubscriptionInterface::VAULT_PAYMENT_TOKEN_ID, ['notnull' => true]);

            foreach ($collection as $subscription) {
                $tokenId = $subscription->getVaultPaymentTokenId();
                if ($tokenId === null) {
                    continue;
                }
                try {
                    $token = $this->tokenRepository->getById($tokenId);
                } catch (\Throwable) {
                    continue;
                }
                $expires = $token->getExpiresAt();
                if ($expires === null) {
                    continue;
                }
                if (substr((string) $expires, 0, 10) !== $targetDate) {
                    continue;
                }
                $this->notifier->notifyCardExpiring($subscription, $token);
                $sent++;
            }
        }

        $this->logger->info('subscription.notify.expiring.summary', [
            'thresholds' => $thresholds,
            'sent' => $sent,
        ]);
    }

    /** @return int[] */
    private function loadThresholds(): array
    {
        $raw = (string) ($this->scopeConfig->getValue(self::CONFIG_DAYS_THRESHOLDS) ?: self::DEFAULT_THRESHOLDS);
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $ints = [];
        foreach ($parts as $p) {
            if (ctype_digit($p) && (int) $p > 0) {
                $ints[] = (int) $p;
            }
        }
        return $ints === [] ? [30, 7, 1] : $ints;
    }
}
