<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

/** Immutable snapshot of Magento's EBizCharge customer mapping. */
class CustomerIdentity
{
    public function __construct(
        public readonly int $magentoCustomerId,
        public readonly string $customerId,
        public readonly string $customerInternalId = '',
        public readonly string $customerNumber = '',
        public readonly ?string $lastSyncAt = null,
        public readonly bool $usesStoredMapping = false,
        public readonly string $status = 'local'
    ) {
    }

    public function isComplete(): bool
    {
        return $this->customerId !== ''
            && $this->customerInternalId !== ''
            && $this->customerNumber !== '';
    }
}
