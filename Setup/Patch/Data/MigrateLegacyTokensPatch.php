<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Setup\Patch\Data;

use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Service\Migration\LegacyTokenMigrator;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Runs the legacy-token migration in DRY-RUN mode on every setup:upgrade.
 *
 * The dry-run logs how many tokens would be migrated; it does not write. Operators run
 * `bin/magento gtstudio:ebizcharge:vault:migrate --execute` once they have reviewed the dry-run
 * output and are ready to commit.
 *
 * Why not write here? Because the migration calls EBizCharge per-customer SOAP and could be slow,
 * and because operators must explicitly opt in to writing under the Coexist-Then-Retire plan.
 */
class MigrateLegacyTokensPatch implements DataPatchInterface
{
    public function __construct(
        private readonly LegacyTokenMigrator $migrator,
        private readonly Logger $logger
    ) {
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    public function apply(): self
    {
        try {
            $stats = $this->migrator->migrate(dryRun: true);
            $this->logger->info('vault.migration.dry_run_summary', $stats);
        } catch (\Throwable $e) {
            $this->logger->warning('vault.migration.dry_run_failed', [
                'message' => $e->getMessage(),
            ]);
        }
        return $this;
    }
}
