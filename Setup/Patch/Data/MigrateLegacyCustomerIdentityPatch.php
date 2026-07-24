<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/** Copies legacy vendor-module customer_entity identity values into the module-owned mapping table. */
class MigrateLegacyCustomerIdentityPatch implements DataPatchInterface
{
    private const LEGACY_COLUMNS = [
        'ec_cust_id',
        'ec_cust_internalid',
        'ec_cust_token',
        'ec_cust_sync_status',
        'ec_cust_lastsyncdate',
    ];

    public function __construct(private readonly ModuleDataSetupInterface $moduleDataSetup)
    {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $customerTable = $this->moduleDataSetup->getTable('customer_entity');
        foreach (self::LEGACY_COLUMNS as $column) {
            if (!$connection->tableColumnExists($customerTable, $column)) {
                return $this;
            }
        }

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($customerTable, array_merge(['entity_id'], self::LEGACY_COLUMNS))
                ->where(
                    "COALESCE(ec_cust_id, '') <> ''"
                    . " OR COALESCE(ec_cust_internalid, '') <> ''"
                    . " OR COALESCE(ec_cust_token, '') <> ''"
                )
        );
        if ($rows === []) {
            return $this;
        }

        $target = $this->moduleDataSetup->getTable('gtstudio_ebizcharge_customer_identity');
        foreach ($rows as $row) {
            $magentoCustomerId = (int) $row['entity_id'];
            $customerId = trim((string) ($row['ec_cust_id'] ?? ''));
            $connection->insertOnDuplicate($target, [
                'customer_id' => $magentoCustomerId,
                'ebiz_customer_id' => $customerId !== '' ? $customerId : (string) $magentoCustomerId,
                'customer_internal_id' => $this->nullable($row['ec_cust_internalid'] ?? null),
                'customer_number' => $this->nullable($row['ec_cust_token'] ?? null),
                'sync_direction' => $this->nullable($row['ec_cust_sync_status'] ?? null),
                'last_synced_at' => $this->nullable($row['ec_cust_lastsyncdate'] ?? null),
            ], [
                'ebiz_customer_id',
                'customer_internal_id',
                'customer_number',
                'sync_direction',
                'last_synced_at',
            ]);
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
