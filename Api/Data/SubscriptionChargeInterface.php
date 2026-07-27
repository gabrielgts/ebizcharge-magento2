<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Api\Data;

/** @api */
interface SubscriptionChargeInterface
{
    public const ENTITY_ID = 'entity_id';
    public const SUBSCRIPTION_ID = 'subscription_id';
    public const SCHEDULED_FOR = 'scheduled_for';
    public const ATTEMPTED_AT = 'attempted_at';
    public const STATUS = 'status';
    public const ATTEMPT_COUNT = 'attempt_count';
    public const MAGENTO_ORDER_ID = 'magento_order_id';
    public const GATEWAY_REF_NUM = 'gateway_ref_num';
    public const ERROR_CODE = 'error_code';
    public const ERROR_MESSAGE = 'error_message';
    public const CORRELATION_ID = 'correlation_id';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public function getEntityId(): ?int;
    public function setEntityId($id);

    public function getSubscriptionId(): int;
    public function setSubscriptionId(int $id): self;

    public function getScheduledFor(): string;
    public function setScheduledFor(string $datetime): self;

    public function getAttemptedAt(): ?string;
    public function setAttemptedAt(?string $datetime): self;

    public function getStatus(): string;
    public function setStatus(string $status): self;

    public function getAttemptCount(): int;
    public function setAttemptCount(int $count): self;

    public function getMagentoOrderId(): ?int;
    public function setMagentoOrderId(?int $orderId): self;

    public function getGatewayRefNum(): ?string;
    public function setGatewayRefNum(?string $refNum): self;

    public function getErrorCode(): ?string;
    public function setErrorCode(?string $code): self;

    public function getErrorMessage(): ?string;
    public function setErrorMessage(?string $message): self;

    public function getCorrelationId(): string;
    public function setCorrelationId(string $id): self;

    public function getCreatedAt(): ?string;
    public function getUpdatedAt(): ?string;
}
