<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class DebugTrace extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('gtstudio_ebizcharge_debug_trace', 'entity_id');
    }
}
