<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Plugin\Adminhtml;

use Gtstudio\Ebizcharge\Service\CustomerIdentityManager;
use Magento\Customer\Model\Customer\DataProviderWithDefaultAddresses;

/** Adds module-owned identity values to the standard customer Admin form data source. */
class CustomerIdentityDataProviderPlugin
{
    public function __construct(private readonly CustomerIdentityManager $identityManager)
    {
    }

    public function afterGetData(DataProviderWithDefaultAddresses $subject, array $result): array
    {
        foreach ($result as $customerId => $data) {
            if (!is_numeric($customerId) || (int) $customerId <= 0 || !is_array($data)) {
                continue;
            }
            try {
                $identity = $this->identityManager->getLocal((int) $customerId);
            } catch (\Throwable) {
                continue;
            }
            $result[$customerId]['ebizcharge_identity'] = [
                'ebiz_customer_id' => $identity->usesStoredMapping ? $identity->customerId : '',
                'customer_internal_id' => $identity->customerInternalId,
                'customer_number' => $identity->customerNumber,
                'mapping_status' => $identity->isComplete()
                    ? (string) __('Complete')
                    : (string) __('Incomplete — checkout will retry synchronization'),
                'last_synced_at' => $identity->lastSyncAt,
            ];
        }

        return $result;
    }
}
