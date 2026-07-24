<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model\ResourceModel\DebugTrace;

use Gtstudio\Ebizcharge\Model\DebugTrace;
use Gtstudio\Ebizcharge\Model\ResourceModel\DebugTrace as DebugTraceResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /** @var string */
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(DebugTrace::class, DebugTraceResource::class);
    }
}
