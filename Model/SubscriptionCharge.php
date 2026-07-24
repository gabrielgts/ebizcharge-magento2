<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionChargeInterface;
use Magento\Framework\Model\AbstractModel;

class SubscriptionCharge extends AbstractModel implements SubscriptionChargeInterface
{
    protected function _construct(): void
    {
        $this->_init(\Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionCharge::class);
    }

    public function getEntityId(): ?int
    {
        $value = $this->getData(self::ENTITY_ID);
        return $value === null ? null : (int) $value;
    }

    public function getSubscriptionId(): int
    {
        return (int) $this->getData(self::SUBSCRIPTION_ID);
    }

    public function setSubscriptionId(int $id): self
    {
        return $this->setData(self::SUBSCRIPTION_ID, $id);
    }

    public function getScheduledFor(): string
    {
        return (string) $this->getData(self::SCHEDULED_FOR);
    }

    public function setScheduledFor(string $datetime): self
    {
        return $this->setData(self::SCHEDULED_FOR, $datetime);
    }

    public function getAttemptedAt(): ?string
    {
        $value = $this->getData(self::ATTEMPTED_AT);
        return $value === null ? null : (string) $value;
    }

    public function setAttemptedAt(?string $datetime): self
    {
        return $this->setData(self::ATTEMPTED_AT, $datetime);
    }

    public function getStatus(): string
    {
        return (string) $this->getData(self::STATUS);
    }

    public function setStatus(string $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getAttemptCount(): int
    {
        return (int) $this->getData(self::ATTEMPT_COUNT);
    }

    public function setAttemptCount(int $count): self
    {
        return $this->setData(self::ATTEMPT_COUNT, $count);
    }

    public function getMagentoOrderId(): ?int
    {
        $value = $this->getData(self::MAGENTO_ORDER_ID);
        return $value === null ? null : (int) $value;
    }

    public function setMagentoOrderId(?int $orderId): self
    {
        return $this->setData(self::MAGENTO_ORDER_ID, $orderId);
    }

    public function getGatewayRefNum(): ?string
    {
        $value = $this->getData(self::GATEWAY_REF_NUM);
        return $value === null ? null : (string) $value;
    }

    public function setGatewayRefNum(?string $refNum): self
    {
        return $this->setData(self::GATEWAY_REF_NUM, $refNum);
    }

    public function getErrorCode(): ?string
    {
        $value = $this->getData(self::ERROR_CODE);
        return $value === null ? null : (string) $value;
    }

    public function setErrorCode(?string $code): self
    {
        return $this->setData(self::ERROR_CODE, $code);
    }

    public function getErrorMessage(): ?string
    {
        $value = $this->getData(self::ERROR_MESSAGE);
        return $value === null ? null : (string) $value;
    }

    public function setErrorMessage(?string $message): self
    {
        return $this->setData(self::ERROR_MESSAGE, $message);
    }

    public function getCorrelationId(): string
    {
        return (string) $this->getData(self::CORRELATION_ID);
    }

    public function setCorrelationId(string $id): self
    {
        return $this->setData(self::CORRELATION_ID, $id);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData(self::CREATED_AT);
        return $value === null ? null : (string) $value;
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData(self::UPDATED_AT);
        return $value === null ? null : (string) $value;
    }
}
