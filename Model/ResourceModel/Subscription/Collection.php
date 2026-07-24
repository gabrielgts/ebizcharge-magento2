<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model\ResourceModel\Subscription;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Model\Subscription;
use Gtstudio\Ebizcharge\Model\ResourceModel\Subscription as SubscriptionResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /** @var string */
    protected $_idFieldName = SubscriptionInterface::ENTITY_ID;

    protected function _construct(): void
    {
        $this->_init(Subscription::class, SubscriptionResource::class);
    }
}
