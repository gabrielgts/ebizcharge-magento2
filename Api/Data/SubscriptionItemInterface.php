<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Api\Data;

/** @api */
interface SubscriptionItemInterface
{
    public const ENTITY_ID = 'entity_id';
    public const SUBSCRIPTION_ID = 'subscription_id';
    public const PRODUCT_ID = 'product_id';
    public const SKU = 'sku';
    public const QTY = 'qty';
    public const PRICE = 'price';

    public function getEntityId(): ?int;
    public function setEntityId($id);

    public function getSubscriptionId(): int;
    public function setSubscriptionId(int $subscriptionId): self;

    public function getProductId(): int;
    public function setProductId(int $productId): self;

    public function getSku(): string;
    public function setSku(string $sku): self;

    public function getQty(): float;
    public function setQty(float $qty): self;

    public function getPrice(): float;
    public function setPrice(float $price): self;
}
