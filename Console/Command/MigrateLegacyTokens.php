<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Console\Command;

use Gtstudio\Ebizcharge\Service\Migration\LegacyTokenMigrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento gtstudio:ebizcharge:vault:migrate [--dry-run]`
 *
 * Migrates legacy ebizcharge_token rows into Magento_Vault. Dry-run is the default to encourage
 * a "show me the diff first" workflow before any writes.
 */
class MigrateLegacyTokens extends Command
{
    public function __construct(private readonly LegacyTokenMigrator $migrator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('gtstudio:ebizcharge:vault:migrate');
        $this->setDescription('Migrate legacy ebizcharge_token rows into Magento_Vault. Defaults to --dry-run.');
        $this->addOption('execute', null, InputOption::VALUE_NONE, 'Persist changes. Without this, runs read-only.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $execute = (bool) $input->getOption('execute');
        $output->writeln(sprintf(
            '<info>Starting legacy token migration (%s)</info>',
            $execute ? 'EXECUTE — writes will be made' : 'DRY RUN — read only'
        ));

        $stats = $this->migrator->migrate(dryRun: !$execute);

        $output->writeln('');
        $output->writeln(sprintf('Processed legacy customer rows: <comment>%d</comment>', $stats['processed']));
        $output->writeln(sprintf('Card tokens that %s: <info>%d</info>',
            $execute ? 'were created' : 'WOULD be created',
            $stats['migrated_cards']
        ));
        $output->writeln(sprintf('ACH tokens that %s: <info>%d</info>',
            $execute ? 'were created' : 'WOULD be created',
            $stats['migrated_ach']
        ));

        $output->writeln('');
        $output->writeln('<comment>Skipped:</comment>');
        foreach ($stats['skipped'] as $reason => $count) {
            if ($count > 0) {
                $output->writeln(sprintf('  - %s: %d', $reason, $count));
            }
        }

        if ($stats['failed'] > 0) {
            $output->writeln('');
            $output->writeln(sprintf('<error>Failures: %d</error>', $stats['failed']));
            foreach ($stats['errors'] as $err) {
                $output->writeln('  - ' . $err);
            }
            return self::FAILURE;
        }

        if (!$execute) {
            $output->writeln('');
            $output->writeln('<comment>Re-run with --execute to apply.</comment>');
        }

        return self::SUCCESS;
    }
}
