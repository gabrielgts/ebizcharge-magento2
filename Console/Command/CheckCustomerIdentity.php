<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Console\Command;

use Gtstudio\Ebizcharge\Service\CustomerIdentity;
use Gtstudio\Ebizcharge\Service\CustomerIdentityManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Read-only remote verification of one Magento-to-EBizCharge customer mapping. */
class CheckCustomerIdentity extends Command
{
    private const ARG_CUSTOMER_ID = 'customer-id';
    private const OPT_STORE_ID = 'store-id';

    public function __construct(private readonly CustomerIdentityManager $identityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('gtstudio:ebizcharge:customer:check')
            ->setDescription('Verify one EBizCharge customer mapping without creating or changing records.')
            ->addArgument(self::ARG_CUSTOMER_ID, InputArgument::REQUIRED, 'Magento customer entity ID')
            ->addOption(self::OPT_STORE_ID, null, InputOption::VALUE_REQUIRED, 'Magento store ID for credentials', '0');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $customerId = (int) $input->getArgument(self::ARG_CUSTOMER_ID);
        $storeId = (int) $input->getOption(self::OPT_STORE_ID);
        if ($customerId <= 0 || $storeId < 0) {
            $output->writeln('<error>Customer ID must be positive and store ID cannot be negative.</error>');
            return self::INVALID;
        }

        try {
            $identity = $this->identityManager->check($customerId, $storeId);
            $this->renderIdentity($output, $identity);
            $output->writeln('<info>Remote identity verified. No Magento or EBizCharge data was changed.</info>');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(sprintf(
                '<error>Verification failed (%s). See the EBizCharge debug trace for details.</error>',
                $e::class
            ));
            return self::FAILURE;
        }
    }

    private function renderIdentity(OutputInterface $output, CustomerIdentity $identity): void
    {
        $output->writeln('Magento customer: ' . $identity->magentoCustomerId);
        $output->writeln('EBizCharge CustomerID: ' . $identity->customerId);
        $output->writeln('EBizCharge CustomerInternalId: ' . $identity->customerInternalId);
        $output->writeln('EBizCharge CustNum: ' . $identity->customerNumber);
    }
}
