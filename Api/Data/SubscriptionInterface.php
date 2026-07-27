<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Api\Data;

/** @api */
interface SubscriptionInterface
{
    public const ENTITY_ID = 'entity_id';
    public const CUSTOMER_ID = 'customer_id';
    public const VAULT_PAYMENT_TOKEN_ID = 'vault_payment_token_id';
    public const STATUS = 'status';
    public const FREQUENCY = 'frequency';
    public const AMOUNT = 'amount';
    public const CURRENCY_CODE = 'currency_code';
    public const STORE_ID = 'store_id';
    public const NEXT_BILL_DATE = 'next_bill_date';
    public const LAST_CHARGED_AT = 'last_charged_at';
    public const START_DATE = 'start_date';
    public const END_DATE = 'end_date';
    public const MAX_CYCLES = 'max_cycles';
    public const COMPLETED_CYCLES = 'completed_cycles';
    public const FAILURE_COUNT = 'failure_count';
    public const LABEL = 'label';
    public const SOURCE_ORDER_ID = 'source_order_id';
    public const EBIZCHARGE_RECURRING_ID = 'ebizcharge_recurring_id';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILING = 'failing';
    public const STATUS_COMPLETED = 'completed';

    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_BIWEEKLY = 'bi-weekly';
    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_BIMONTHLY = 'bi-monthly';
    public const FREQUENCY_QUARTERLY = 'quarterly';
    public const FREQUENCY_BIANNUALLY = 'bi-annually';
    public const FREQUENCY_ANNUALLY = 'annually';

    public function getEntityId(): ?int;
    public function setEntityId($id);

    public function getCustomerId(): int;
    public function setCustomerId(int $customerId): self;

    public function getVaultPaymentTokenId(): ?int;
    public function setVaultPaymentTokenId(?int $tokenId): self;

    public function getStatus(): string;
    public function setStatus(string $status): self;

    public function getFrequency(): string;
    public function setFrequency(string $frequency): self;

    public function getAmount(): float;
    public function setAmount(float $amount): self;

    public function getCurrencyCode(): string;
    public function setCurrencyCode(string $currency): self;

    public function getStoreId(): int;
    public function setStoreId(int $storeId): self;

    public function getNextBillDate(): string;
    public function setNextBillDate(string $date): self;

    public function getLastChargedAt(): ?string;
    public function setLastChargedAt(?string $datetime): self;

    public function getStartDate(): string;
    public function setStartDate(string $date): self;

    public function getEndDate(): ?string;
    public function setEndDate(?string $date): self;

    public function getMaxCycles(): ?int;
    public function setMaxCycles(?int $max): self;

    public function getCompletedCycles(): int;
    public function setCompletedCycles(int $count): self;

    public function getFailureCount(): int;
    public function setFailureCount(int $count): self;

    public function getLabel(): ?string;
    public function setLabel(?string $label): self;

    public function getSourceOrderId(): ?int;
    public function setSourceOrderId(?int $orderId): self;

    public function getEbizchargeRecurringId(): ?string;
    public function setEbizchargeRecurringId(?string $id): self;

    public function getCreatedAt(): ?string;

    public function getUpdatedAt(): ?string;
}
