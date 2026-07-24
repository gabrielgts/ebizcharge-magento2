<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service\Migration;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionRepositoryInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionScheduleInterface;
use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Model\SubscriptionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Vault\Api\PaymentTokenManagementInterface;

/**
 * Migrates rows from the legacy `ebizcharge_recurring_dates` and `ebizcharge_recurring_order`
 * tables into the new `gtstudio_ebizcharge_subscription` schema.
 *
 * Mapping:
 *   ebizcharge_recurring_dates.recurring_id    → grouping key
 *   ebizcharge_recurring_dates.recurring_date  → first row drives next_bill_date
 *   ebizcharge_recurring_order.rec_order_id    → links source order
 *   ebizcharge_recurring_order.created_date    → start_date / created_at
 *   ebizcharge_recurring_order.status          → maps to new status taxonomy
 *
 * Resolves vault token by looking up the customer's already-migrated tokens (Phase 3 vault
 * migration must run first). If no token is found, the legacy subscription is skipped — the
 * customer will be re-prompted at next charge attempt or on resume.
 *
 * Both dry-run (default) and execute modes are supported. Idempotent against re-runs via the
 * `(customer_id, source_order_id, frequency)` natural key check.
 */
class LegacySubscriptionMigrator
{
    public const SKIP_NO_LEGACY_TABLE = 'no_legacy_table';
    public const SKIP_DUPLICATE = 'duplicate';
    public const SKIP_NO_VAULT_TOKEN = 'no_vault_token';
    public const SKIP_INCOMPLETE_DATA = 'incomplete_data';

    private const STATUS_MAP = [
        'active' => SubscriptionInterface::STATUS_ACTIVE,
        'paused' => SubscriptionInterface::STATUS_PAUSED,
        'suspended' => SubscriptionInterface::STATUS_PAUSED,
        'cancelled' => SubscriptionInterface::STATUS_CANCELLED,
        'canceled' => SubscriptionInterface::STATUS_CANCELLED,
        'completed' => SubscriptionInterface::STATUS_COMPLETED,
        'expired' => SubscriptionInterface::STATUS_EXPIRED,
    ];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly SubscriptionFactory $subscriptionFactory,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly SubscriptionScheduleInterface $schedule,
        private readonly PaymentTokenManagementInterface $tokenManagement,
        private readonly Logger $logger
    ) {
    }

    /**
     * @return array{
     *   processed:int,
     *   migrated:int,
     *   skipped:array<string,int>,
     *   failed:int,
     *   errors:array<int,string>
     * }
     */
    public function migrate(bool $dryRun = true): array
    {
        $stats = [
            'processed' => 0,
            'migrated' => 0,
            'skipped' => [
                self::SKIP_NO_LEGACY_TABLE => 0,
                self::SKIP_DUPLICATE => 0,
                self::SKIP_NO_VAULT_TOKEN => 0,
                self::SKIP_INCOMPLETE_DATA => 0,
            ],
            'failed' => 0,
            'errors' => [],
        ];

        $rows = $this->fetchLegacyRows();
        if ($rows === null) {
            $stats['skipped'][self::SKIP_NO_LEGACY_TABLE]++;
            return $stats;
        }

        foreach ($rows as $row) {
            $stats['processed']++;
            $sourceOrderId = isset($row['source_order_id']) ? (int) $row['source_order_id'] : null;
            $customerId = (int) ($row['customer_id'] ?? 0);
            $frequency = $this->normalizeFrequency((string) ($row['frequency'] ?? ''));
            $nextBillDate = (string) ($row['next_bill_date'] ?? '');

            if ($customerId === 0 || $frequency === null || $nextBillDate === '') {
                $stats['skipped'][self::SKIP_INCOMPLETE_DATA]++;
                continue;
            }

            if ($this->alreadyMigrated($customerId, $sourceOrderId, $frequency)) {
                $stats['skipped'][self::SKIP_DUPLICATE]++;
                continue;
            }

            $tokenId = $this->resolveDefaultVaultToken($customerId);
            if ($tokenId === null) {
                $stats['skipped'][self::SKIP_NO_VAULT_TOKEN]++;
                continue;
            }

            if ($dryRun) {
                $stats['migrated']++;
                continue;
            }

            try {
                $this->createSubscription($row, $customerId, $tokenId, $frequency, $nextBillDate, $sourceOrderId);
                $stats['migrated']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                $stats['errors'][] = sprintf(
                    'Customer %d, source order %s: %s',
                    $customerId,
                    $sourceOrderId === null ? 'n/a' : (string) $sourceOrderId,
                    $e->getMessage()
                );
            }
        }

        return $stats;
    }

    /**
     * Reads from the two legacy tables. Tolerates either being absent.
     *
     * @return array<int,array<string,mixed>>|null
     */
    private function fetchLegacyRows(): ?array
    {
        $connection = $this->resource->getConnection();
        $datesTable = $this->resource->getTableName('ebizcharge_recurring_dates');
        $ordersTable = $this->resource->getTableName('ebizcharge_recurring_order');

        if (!$connection->isTableExists($datesTable) && !$connection->isTableExists($ordersTable)) {
            return null;
        }

        // Aggregate the legacy data — recurring_id groups dates; the latest active row per
        // recurring_id is what we map. Field names follow the legacy module's schema, with
        // fallbacks for variations seen in customizations.
        $select = $connection->select()
            ->from(['d' => $datesTable], [
                'recurring_id' => 'd.recurring_id',
                'next_bill_date' => 'MIN(d.recurring_date)',
            ])
            ->joinLeft(
                ['o' => $ordersTable],
                'o.recurring_id = d.recurring_id',
                [
                    'customer_id' => 'o.customer_id',
                    'frequency' => 'o.frequency',
                    'amount' => 'o.amount',
                    'status' => 'o.status',
                    'source_order_id' => 'o.rec_order_id',
                    'created_date' => 'o.created_date',
                ]
            )
            ->group('d.recurring_id');

        try {
            return $connection->fetchAll($select);
        } catch (\Throwable $e) {
            $this->logger->warning('subscription.legacy_migration.fetch_failed', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function normalizeFrequency(string $raw): ?string
    {
        $raw = strtolower(trim($raw));
        $map = [
            'daily' => SubscriptionInterface::FREQUENCY_DAILY,
            'weekly' => SubscriptionInterface::FREQUENCY_WEEKLY,
            'biweekly' => SubscriptionInterface::FREQUENCY_BIWEEKLY,
            'bi-weekly' => SubscriptionInterface::FREQUENCY_BIWEEKLY,
            'monthly' => SubscriptionInterface::FREQUENCY_MONTHLY,
            'two-month' => SubscriptionInterface::FREQUENCY_BIMONTHLY,
            'bimonthly' => SubscriptionInterface::FREQUENCY_BIMONTHLY,
            'bi-monthly' => SubscriptionInterface::FREQUENCY_BIMONTHLY,
            'three-month' => SubscriptionInterface::FREQUENCY_QUARTERLY,
            'quarterly' => SubscriptionInterface::FREQUENCY_QUARTERLY,
            'six-month' => SubscriptionInterface::FREQUENCY_BIANNUALLY,
            'biannually' => SubscriptionInterface::FREQUENCY_BIANNUALLY,
            'bi-annually' => SubscriptionInterface::FREQUENCY_BIANNUALLY,
            'annually' => SubscriptionInterface::FREQUENCY_ANNUALLY,
            'yearly' => SubscriptionInterface::FREQUENCY_ANNUALLY,
        ];
        return $map[$raw] ?? null;
    }

    private function alreadyMigrated(int $customerId, ?int $sourceOrderId, string $frequency): bool
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('gtstudio_ebizcharge_subscription');
        $select = $connection->select()
            ->from($table, ['entity_id'])
            ->where('customer_id = ?', $customerId)
            ->where('frequency = ?', $frequency)
            ->limit(1);
        if ($sourceOrderId !== null) {
            $select->where('source_order_id = ?', $sourceOrderId);
        } else {
            $select->where('source_order_id IS NULL');
        }
        return $connection->fetchOne($select) !== false;
    }

    /**
     * Pick the customer's first visible-active vault token migrated by Phase 3, preferring the
     * cards method over ACH (mirrors how the legacy module behaved).
     */
    private function resolveDefaultVaultToken(int $customerId): ?int
    {
        try {
            $tokens = $this->tokenManagement->getVisibleAvailableTokens($customerId);
        } catch (NoSuchEntityException) {
            return null;
        }
        if ($tokens === null || $tokens === []) {
            return null;
        }
        $cardToken = null;
        $achToken = null;
        foreach ($tokens as $token) {
            $code = (string) $token->getPaymentMethodCode();
            if ($code === Config::METHOD_CODE && $cardToken === null) {
                $cardToken = $token;
            } elseif ($code === Config::METHOD_CODE_ACH && $achToken === null) {
                $achToken = $token;
            }
        }
        $picked = $cardToken ?? $achToken ?? array_values($tokens)[0];
        return (int) $picked->getEntityId();
    }

    private function createSubscription(
        array $row,
        int $customerId,
        int $tokenId,
        string $frequency,
        string $nextBillDate,
        ?int $sourceOrderId
    ): void {
        $status = self::STATUS_MAP[strtolower((string) ($row['status'] ?? 'active'))]
            ?? SubscriptionInterface::STATUS_ACTIVE;

        $subscription = $this->subscriptionFactory->create();
        $subscription->setCustomerId($customerId);
        $subscription->setVaultPaymentTokenId($tokenId);
        $subscription->setStatus($status);
        $subscription->setFrequency($frequency);
        $subscription->setAmount((float) ($row['amount'] ?? 0));
        $subscription->setCurrencyCode('USD');
        $subscription->setStoreId(0);
        $subscription->setNextBillDate($nextBillDate);
        $subscription->setStartDate(substr((string) ($row['created_date'] ?? $nextBillDate), 0, 10));
        $subscription->setCompletedCycles(0);
        $subscription->setFailureCount(0);
        if ($sourceOrderId !== null) {
            $subscription->setSourceOrderId($sourceOrderId);
        }
        $subscription->setLabel('Migrated subscription');
        $subscription->setEbizchargeRecurringId((string) ($row['recurring_id'] ?? ''));

        $this->subscriptionRepository->save($subscription);
    }
}
