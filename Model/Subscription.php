<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Magento\Framework\Model\AbstractModel;

class Subscription extends AbstractModel implements SubscriptionInterface
{
    protected function _construct(): void
    {
        $this->_init(\Gtstudio\Ebizcharge\Model\ResourceModel\Subscription::class);
    }

    public function getEntityId(): ?int
    {
        $value = $this->getData(self::ENTITY_ID);
        return $value === null ? null : (int) $value;
    }

    public function getCustomerId(): int
    {
        return (int) $this->getData(self::CUSTOMER_ID);
    }

    public function setCustomerId(int $customerId): self
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    public function getVaultPaymentTokenId(): ?int
    {
        $value = $this->getData(self::VAULT_PAYMENT_TOKEN_ID);
        return $value === null ? null : (int) $value;
    }

    public function setVaultPaymentTokenId(?int $tokenId): self
    {
        return $this->setData(self::VAULT_PAYMENT_TOKEN_ID, $tokenId);
    }

    public function getStatus(): string
    {
        return (string) $this->getData(self::STATUS);
    }

    public function setStatus(string $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getFrequency(): string
    {
        return (string) $this->getData(self::FREQUENCY);
    }

    public function setFrequency(string $frequency): self
    {
        return $this->setData(self::FREQUENCY, $frequency);
    }

    public function getAmount(): float
    {
        return (float) $this->getData(self::AMOUNT);
    }

    public function setAmount(float $amount): self
    {
        return $this->setData(self::AMOUNT, $amount);
    }

    public function getCurrencyCode(): string
    {
        return (string) $this->getData(self::CURRENCY_CODE);
    }

    public function setCurrencyCode(string $currency): self
    {
        return $this->setData(self::CURRENCY_CODE, $currency);
    }

    public function getStoreId(): int
    {
        return (int) $this->getData(self::STORE_ID);
    }

    public function setStoreId(int $storeId): self
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    public function getNextBillDate(): string
    {
        return (string) $this->getData(self::NEXT_BILL_DATE);
    }

    public function setNextBillDate(string $date): self
    {
        return $this->setData(self::NEXT_BILL_DATE, $date);
    }

    public function getLastChargedAt(): ?string
    {
        $value = $this->getData(self::LAST_CHARGED_AT);
        return $value === null ? null : (string) $value;
    }

    public function setLastChargedAt(?string $datetime): self
    {
        return $this->setData(self::LAST_CHARGED_AT, $datetime);
    }

    public function getStartDate(): string
    {
        return (string) $this->getData(self::START_DATE);
    }

    public function setStartDate(string $date): self
    {
        return $this->setData(self::START_DATE, $date);
    }

    public function getEndDate(): ?string
    {
        $value = $this->getData(self::END_DATE);
        return $value === null ? null : (string) $value;
    }

    public function setEndDate(?string $date): self
    {
        return $this->setData(self::END_DATE, $date);
    }

    public function getMaxCycles(): ?int
    {
        $value = $this->getData(self::MAX_CYCLES);
        return $value === null ? null : (int) $value;
    }

    public function setMaxCycles(?int $max): self
    {
        return $this->setData(self::MAX_CYCLES, $max);
    }

    public function getCompletedCycles(): int
    {
        return (int) $this->getData(self::COMPLETED_CYCLES);
    }

    public function setCompletedCycles(int $count): self
    {
        return $this->setData(self::COMPLETED_CYCLES, $count);
    }

    public function getFailureCount(): int
    {
        return (int) $this->getData(self::FAILURE_COUNT);
    }

    public function setFailureCount(int $count): self
    {
        return $this->setData(self::FAILURE_COUNT, $count);
    }

    public function getLabel(): ?string
    {
        $value = $this->getData(self::LABEL);
        return $value === null ? null : (string) $value;
    }

    public function setLabel(?string $label): self
    {
        return $this->setData(self::LABEL, $label);
    }

    public function getSourceOrderId(): ?int
    {
        $value = $this->getData(self::SOURCE_ORDER_ID);
        return $value === null ? null : (int) $value;
    }

    public function setSourceOrderId(?int $orderId): self
    {
        return $this->setData(self::SOURCE_ORDER_ID, $orderId);
    }

    public function getEbizchargeRecurringId(): ?string
    {
        $value = $this->getData(self::EBIZCHARGE_RECURRING_ID);
        return $value === null ? null : (string) $value;
    }

    public function setEbizchargeRecurringId(?string $id): self
    {
        return $this->setData(self::EBIZCHARGE_RECURRING_ID, $id);
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
