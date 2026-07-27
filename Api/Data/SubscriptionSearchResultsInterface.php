<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/** @api */
interface SubscriptionSearchResultsInterface extends SearchResultsInterface
{
    /** @return SubscriptionInterface[] */
    public function getItems();

    /** @param SubscriptionInterface[] $items */
    public function setItems(array $items);
}
