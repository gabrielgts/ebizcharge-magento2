<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Console\Command;

use Gtstudio\Ebizcharge\Service\CustomerIdentityManager;
use Gtstudio\Ebizcharge\Service\CustomerPayloadBuilder;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Resolves/creates EBizCharge customers and persists their three identity values. */
class SyncCustomerIdentity extends Command
{
    private const ARG_CUSTOMER_ID = 'customer-id';
    private const OPT_STORE_ID = 'store-id';
    private const OPT_ALL = 'all';

    public function __construct(
        private readonly CustomerIdentityManager $identityManager,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerPayloadBuilder $payloadBuilder
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('gtstudio:ebizcharge:customer:sync')
            ->setDescription('Resolve or create EBizCharge customer identities and persist the mapping.')
            ->addArgument(self::ARG_CUSTOMER_ID, InputArgument::OPTIONAL, 'Magento customer entity ID')
            ->addOption(self::OPT_STORE_ID, null, InputOption::VALUE_REQUIRED, 'Magento store ID for credentials', '0')
            ->addOption(self::OPT_ALL, null, InputOption::VALUE_NONE, 'Synchronize every Magento customer');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $customerId = (int) ($input->getArgument(self::ARG_CUSTOMER_ID) ?? 0);
        $all = (bool) $input->getOption(self::OPT_ALL);
        $storeId = (int) $input->getOption(self::OPT_STORE_ID);
        if ($storeId < 0 || ($customerId <= 0 && !$all) || ($customerId > 0 && $all)) {
            $output->writeln(
                '<error>Provide one positive customer-id or --all, but not both; store ID cannot be negative.</error>'
            );
            return self::INVALID;
        }

        $ids = $all ? $this->identityManager->getAllCustomerIds() : [$customerId];
        $success = 0;
        $failed = 0;
        foreach ($ids as $id) {
            try {
                $customer = $this->customerRepository->getById($id);
                $identity = $this->identityManager->sync(
                    $id,
                    $this->payloadBuilder->fromCustomer($customer),
                    $storeId
                );
                ++$success;
                $output->writeln(sprintf(
                    '<info>✓ Customer %d</info> CustomerID=%s CustNum=%s',
                    $id,
                    $identity->customerId,
                    $identity->customerNumber
                ));
            } catch (\Throwable $e) {
                ++$failed;
                $output->writeln(sprintf(
                    '<error>✗ Customer %d</error> %s',
                    $id,
                    $e::class
                ));
            }
        }

        $output->writeln(sprintf(
            'Completed: %d synchronized, %d failed.',
            $success,
            $failed
        ));
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
