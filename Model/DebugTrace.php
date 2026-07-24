<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * One captured SOAP request/response cycle for the admin debug grid.
 *
 * Internal-only — no Api/Data interface because this isn't part of the public surface and the
 * shape will likely evolve as we add more telemetry (3DS results, batch metadata, etc.).
 */
class DebugTrace extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(\Gtstudio\Ebizcharge\Model\ResourceModel\DebugTrace::class);
    }
}
