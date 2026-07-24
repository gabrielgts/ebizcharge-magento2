<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface SubscriptionChargeSearchResultsInterface extends SearchResultsInterface
{
    /** @return SubscriptionChargeInterface[] */
    public function getItems();

    /** @param SubscriptionChargeInterface[] $items */
    public function setItems(array $items);
}
