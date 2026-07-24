<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionCharge;

use Gtstudio\Ebizcharge\Model\SubscriptionCharge;
use Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionCharge as SubscriptionChargeResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /** @var string */
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(SubscriptionCharge::class, SubscriptionChargeResource::class);
    }
}
