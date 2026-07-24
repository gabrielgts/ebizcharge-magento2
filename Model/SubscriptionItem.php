<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionItemInterface;
use Magento\Framework\Model\AbstractModel;

class SubscriptionItem extends AbstractModel implements SubscriptionItemInterface
{
    protected function _construct(): void
    {
        $this->_init(\Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionItem::class);
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

    public function setSubscriptionId(int $subscriptionId): self
    {
        return $this->setData(self::SUBSCRIPTION_ID, $subscriptionId);
    }

    public function getProductId(): int
    {
        return (int) $this->getData(self::PRODUCT_ID);
    }

    public function setProductId(int $productId): self
    {
        return $this->setData(self::PRODUCT_ID, $productId);
    }

    public function getSku(): string
    {
        return (string) $this->getData(self::SKU);
    }

    public function setSku(string $sku): self
    {
        return $this->setData(self::SKU, $sku);
    }

    public function getQty(): float
    {
        return (float) $this->getData(self::QTY);
    }

    public function setQty(float $qty): self
    {
        return $this->setData(self::QTY, $qty);
    }

    public function getPrice(): float
    {
        return (float) $this->getData(self::PRICE);
    }

    public function setPrice(float $price): self
    {
        return $this->setData(self::PRICE, $price);
    }
}
