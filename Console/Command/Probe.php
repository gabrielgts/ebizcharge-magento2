<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Console\Command;

use Gtstudio\Ebizcharge\Service\Gateway\ConnectionProbe;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Probes the configured EBizCharge connection. */
class Probe extends Command
{
    public function __construct(private readonly ConnectionProbe $probe)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('gtstudio:ebizcharge:probe');
        $this->setDescription(
            'Test the configured EBizCharge endpoint and report latency or failure.'
        );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Probing EBizCharge gateway…</info>');
        $result = $this->probe->probe();

        if ($result['success']) {
            $output->writeln(sprintf(
                '<info>✓ Connected</info> <comment>(%d ms)</comment>',
                $result['latency_ms']
            ));
            return self::SUCCESS;
        }

        $output->writeln(sprintf(
            '<error>✗ %s</error> <comment>(%d ms)</comment>',
            $result['message'],
            $result['latency_ms']
        ));
        return self::FAILURE;
    }
}
