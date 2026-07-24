<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Subscription extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('gtstudio_ebizcharge_subscription', 'entity_id');
    }
}
