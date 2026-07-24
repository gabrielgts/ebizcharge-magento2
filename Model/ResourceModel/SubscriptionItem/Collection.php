<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionItem;

use Gtstudio\Ebizcharge\Model\SubscriptionItem;
use Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionItem as SubscriptionItemResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /** @var string */
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(SubscriptionItem::class, SubscriptionItemResource::class);
    }
}
