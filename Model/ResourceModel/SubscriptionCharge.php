<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class SubscriptionCharge extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('gtstudio_ebizcharge_subscription_charge', 'entity_id');
    }
}
