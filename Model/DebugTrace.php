<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model;

use Magento\Framework\Model\AbstractModel;

/** Represents a redacted SOAP debug trace. */
class DebugTrace extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(\Gtstudio\Ebizcharge\Model\ResourceModel\DebugTrace::class);
    }
}
