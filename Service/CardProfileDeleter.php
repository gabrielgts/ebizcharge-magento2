<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Gateway\Http\SoapMethodClient;
use Magento\Store\Model\StoreManagerInterface;

/** Deletes an EBizCharge card profile addressed by a Magento Vault gateway token. */
class CardProfileDeleter
{
    public function __construct(
        private readonly SoapMethodClient $soap,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function delete(string $gatewayToken, ?int $websiteId = null): void
    {
        [$custNum, $methodId] = $this->parseToken($gatewayToken);
        $storeId = $this->resolveStoreId($websiteId);
        $response = $this->soap->call('DeleteCustomerPaymentMethodProfile', [
            'securityToken' => [
                'UserId' => $this->config->getUserId($storeId),
                'SecurityId' => $this->config->getSecurityId($storeId),
                'Password' => $this->config->getPassword($storeId),
            ],
            'customerToken' => $custNum,
            'paymentMethodId' => $methodId,
        ], ['store_id' => $storeId]);

        $result = $response['DeleteCustomerPaymentMethodProfileResult'] ?? null;
        if (!filter_var($result, FILTER_VALIDATE_BOOLEAN)) {
            throw new \RuntimeException('EBizCharge did not confirm payment profile deletion.');
        }
    }

    /** @return array{0:string,1:string} */
    private function parseToken(string $gatewayToken): array
    {
        if (!str_contains($gatewayToken, ':')) {
            throw new \InvalidArgumentException('Malformed EBizCharge gateway token.');
        }
        [$custNum, $methodId] = array_map('trim', explode(':', $gatewayToken, 2));
        if ($custNum === '' || $methodId === '') {
            throw new \InvalidArgumentException('Malformed EBizCharge gateway token.');
        }
        return [$custNum, $methodId];
    }

    private function resolveStoreId(?int $websiteId): ?int
    {
        if ($websiteId === null || $websiteId <= 0) {
            return null;
        }
        try {
            $store = $this->storeManager->getWebsite($websiteId)->getDefaultStore();
            return $store === null ? null : (int) $store->getId();
        } catch (\Throwable) {
            return null;
        }
    }
}
