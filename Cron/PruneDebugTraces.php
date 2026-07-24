<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Cron;

use Gtstudio\Ebizcharge\Logger\Logger;
use Magento\Framework\App\ResourceConnection;

/**
 * Daily — deletes debug-trace rows older than the retention window (7 days, hard-coded for now;
 * make admin-configurable when there's a use case).
 *
 * Trace data can grow fast under heavy traffic; without pruning we'd have a slow-burn
 * problem identical to the legacy module's unbounded `econnect.log` growth.
 */
class PruneDebugTraces
{
    private const RETENTION_DAYS = 7;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Logger $logger
    ) {
    }

    public function execute(): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('gtstudio_ebizcharge_debug_trace');
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . self::RETENTION_DAYS . ' days'));

        try {
            $deleted = $connection->delete($table, ['created_at < ?' => $cutoff]);
            $this->logger->info('debug_trace.prune', [
                'cutoff' => $cutoff,
                'deleted' => $deleted,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('debug_trace.prune.failed', ['message' => $e->getMessage()]);
        }
    }
}
