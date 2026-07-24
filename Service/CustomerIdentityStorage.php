<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Parameterized persistence for the module-owned customer identity table.
 *
 * The customer-facing identifier is intentionally not globally constrained in declarative
 * schema because an existing installation may contain duplicates. We detect and surface those
 * records without making setup:upgrade fail on legacy data.
 */
class CustomerIdentityStorage
{
    private const CUSTOMER_TABLE = 'customer_entity';
    private const IDENTITY_TABLE = 'gtstudio_ebizcharge_customer_identity';

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function get(int $magentoCustomerId): CustomerIdentity
    {
        $connection = $this->resource->getConnection();
        $customerTable = $this->resource->getTableName(self::CUSTOMER_TABLE);
        $identityTable = $this->resource->getTableName(self::IDENTITY_TABLE);
        $row = $connection->fetchRow(
            $connection->select()
                ->from(['customer' => $customerTable], ['entity_id'])
                ->joinLeft(['identity' => $identityTable], 'identity.customer_id = customer.entity_id', [
                    'ebiz_customer_id',
                    'customer_internal_id',
                    'customer_number',
                    'sync_direction',
                    'last_synced_at',
                ])
                ->where('customer.entity_id = ?', $magentoCustomerId)
        );

        if (!is_array($row)) {
            throw new NoSuchEntityException(__('Customer with ID "%1" does not exist.', $magentoCustomerId));
        }

        $mappedCustomerId = trim((string) ($row['ebiz_customer_id'] ?? ''));
        return new CustomerIdentity(
            $magentoCustomerId,
            $mappedCustomerId !== '' ? $mappedCustomerId : (string) $magentoCustomerId,
            trim((string) ($row['customer_internal_id'] ?? '')),
            trim((string) ($row['customer_number'] ?? '')),
            isset($row['last_synced_at']) ? (string) $row['last_synced_at'] : null,
            $mappedCustomerId !== '',
            trim((string) ($row['sync_direction'] ?? '')) !== '' ? 'cached' : 'local'
        );
    }

    public function assertCustomerIdAvailable(string $customerId, int $magentoCustomerId): void
    {
        $customerId = trim($customerId);
        if ($customerId === '') {
            return;
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::IDENTITY_TABLE);
        $duplicate = $connection->fetchOne(
            $connection->select()
                ->from($table, ['customer_id'])
                ->where('ebiz_customer_id = ?', $customerId)
                ->where('customer_id <> ?', $magentoCustomerId)
                ->limit(1)
        );

        if ($duplicate !== false) {
            throw new LocalizedException(__(
                'EBizCharge Customer ID "%1" is already mapped to Magento customer %2.',
                $customerId,
                (int) $duplicate
            ));
        }
    }

    public function saveResolved(CustomerIdentity $identity): void
    {
        $this->assertCustomerIdAvailable($identity->customerId, $identity->magentoCustomerId);

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::IDENTITY_TABLE);
        $connection->insertOnDuplicate($table, [
            'customer_id' => $identity->magentoCustomerId,
            'ebiz_customer_id' => $identity->customerId,
            'customer_internal_id' => $identity->customerInternalId,
            'customer_number' => $identity->customerNumber,
            // Legacy value 1 means Magento/EConnect outbound synchronization.
            'sync_direction' => '1',
            'last_synced_at' => gmdate('Y-m-d H:i:s'),
        ], [
            'ebiz_customer_id',
            'customer_internal_id',
            'customer_number',
            'sync_direction',
            'last_synced_at',
        ]);
    }

    public function saveCustomerNumber(int $magentoCustomerId, string $customerNumber): void
    {
        $customerNumber = trim($customerNumber);
        if ($customerNumber === '' || $customerNumber === '0') {
            return;
        }

        $identity = $this->get($magentoCustomerId);
        $connection = $this->resource->getConnection();
        $connection->insertOnDuplicate(
            $this->resource->getTableName(self::IDENTITY_TABLE),
            [
                'customer_id' => $magentoCustomerId,
                'ebiz_customer_id' => $identity->customerId,
                'customer_internal_id' => $identity->customerInternalId ?: null,
                'customer_number' => $customerNumber,
                'sync_direction' => '1',
                'last_synced_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['customer_number', 'sync_direction', 'last_synced_at']
        );
    }

    public function setCustomerId(int $magentoCustomerId, string $customerId): void
    {
        $customerId = trim($customerId);
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::IDENTITY_TABLE);
        if ($customerId === '') {
            $connection->delete($table, ['customer_id = ?' => $magentoCustomerId]);
            return;
        }

        $this->assertCustomerIdAvailable($customerId, $magentoCustomerId);
        $connection->insertOnDuplicate($table, [
            'customer_id' => $magentoCustomerId,
            'ebiz_customer_id' => $customerId,
            'customer_internal_id' => null,
            'customer_number' => null,
            'sync_direction' => null,
            'last_synced_at' => null,
        ], [
            'ebiz_customer_id',
            'customer_internal_id',
            'customer_number',
            'sync_direction',
            'last_synced_at',
        ]);
    }

    /** @return int[] */
    public function getAllCustomerIds(): array
    {
        $connection = $this->resource->getConnection();
        $ids = $connection->fetchCol(
            $connection->select()
                ->from($this->resource->getTableName(self::CUSTOMER_TABLE), ['entity_id'])
                ->order('entity_id ASC')
        );
        return array_map('intval', $ids);
    }
}
