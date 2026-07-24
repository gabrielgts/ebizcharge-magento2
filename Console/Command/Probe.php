<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Console\Command;

use Gtstudio\Ebizcharge\Service\Gateway\ConnectionProbe;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento gtstudio:ebizcharge:probe`
 *
 * Round-trips a no-op SOAP call against the configured endpoint and reports the result. Useful
 * during install, after credential rotation, and as a quick smoke test from a deploy script.
 */
class Probe extends Command
{
    public function __construct(private readonly ConnectionProbe $probe)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('gtstudio:ebizcharge:probe');
        $this->setDescription('Round-trip the configured EBizCharge endpoint with the saved credentials. Returns latency on success or the error message on failure.');
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
