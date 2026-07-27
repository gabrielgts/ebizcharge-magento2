<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Cron;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Model\ResourceModel\Subscription\Collection as SubscriptionCollection;
use Gtstudio\Ebizcharge\Model\ResourceModel\Subscription\CollectionFactory as SubscriptionCollectionFactory;
use Gtstudio\Ebizcharge\Service\SubscriptionEmailNotifier;
use Magento\Framework\App\Config\ScopeConfigInterface;

/** Sends upcoming-charge reminders for active subscriptions. */
class NotifyUpcomingCharges
{
    public const CONFIG_DAYS_AHEAD = 'payment/gtstudio_ebizcharge/subscription_upcoming_charge_email_days';
    private const DEFAULT_DAYS_AHEAD = 3;

    public function __construct(
        private readonly SubscriptionCollectionFactory $subscriptionCollectionFactory,
        private readonly SubscriptionEmailNotifier $notifier,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Logger $logger
    ) {
    }

    public function execute(): void
    {
        $daysAhead = (int) ($this->scopeConfig->getValue(self::CONFIG_DAYS_AHEAD) ?: self::DEFAULT_DAYS_AHEAD);
        $target = date('Y-m-d', strtotime("+{$daysAhead} days"));

        /** @var SubscriptionCollection $collection */
        $collection = $this->subscriptionCollectionFactory->create();
        $collection->addFieldToFilter(SubscriptionInterface::STATUS, SubscriptionInterface::STATUS_ACTIVE)
            ->addFieldToFilter(SubscriptionInterface::NEXT_BILL_DATE, $target);

        $sent = 0;
        foreach ($collection as $subscription) {
            $this->notifier->notifyUpcomingCharge($subscription);
            $sent++;
        }

        $this->logger->info('subscription.notify.upcoming.summary', [
            'days_ahead' => $daysAhead,
            'target_date' => $target,
            'sent' => $sent,
        ]);
    }
}
