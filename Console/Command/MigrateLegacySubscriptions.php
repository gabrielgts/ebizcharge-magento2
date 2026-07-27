<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Console\Command;

use Gtstudio\Ebizcharge\Service\Migration\LegacySubscriptionMigrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Migrates legacy subscriptions with dry-run as the default. */
class MigrateLegacySubscriptions extends Command
{
    public function __construct(private readonly LegacySubscriptionMigrator $migrator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('gtstudio:ebizcharge:subscription:migrate-legacy');
        $this->setDescription(
            'Migrate legacy recurring rows into Magento Vault-backed subscriptions; defaults to dry-run.'
        );
        $this->addOption('execute', null, InputOption::VALUE_NONE, 'Persist changes. Without this, runs read-only.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $execute = (bool) $input->getOption('execute');
        $output->writeln(sprintf(
            '<info>Starting legacy subscription migration (%s)</info>',
            $execute ? 'EXECUTE — writes will be made' : 'DRY RUN — read only'
        ));

        $stats = $this->migrator->migrate(dryRun: !$execute);

        $output->writeln('');
        $output->writeln(sprintf('Processed legacy rows: <comment>%d</comment>', $stats['processed']));
        $output->writeln(sprintf(
            'Subscriptions that %s: <info>%d</info>',
            $execute ? 'were created' : 'WOULD be created',
            $stats['migrated']
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
            $output->writeln(
                '<comment>Run the Vault migration first so customers have subscription tokens.</comment>'
            );
        }

        return self::SUCCESS;
    }
}
