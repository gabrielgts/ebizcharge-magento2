<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

use RuntimeException;
use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Gateway\Http\SoapFaultException;
use Gtstudio\Ebizcharge\Gateway\Http\SoapMethodClient;
use Gtstudio\Ebizcharge\Logger\Logger;

/** Resolves Magento-to-EBizCharge customer identity mappings. */
class CustomerIdentityManager
{
    public function __construct(
        private readonly CustomerIdentityStorage $storage,
        private readonly SoapMethodClient $soap,
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly CorrelationIdProvider $correlationId
    ) {
    }

    public function getLocal(int $magentoCustomerId): CustomerIdentity
    {
        return $this->storage->get($magentoCustomerId);
    }

    /** Resolves checkout identity without blocking payment on remote failure. */
    public function resolveForTransaction(
        int $magentoCustomerId,
        array $customerData,
        ?int $storeId
    ): CustomerIdentity {
        try {
            $local = $this->storage->get($magentoCustomerId);
            if ($local->isComplete()) {
                return $local;
            }
            return $this->sync($magentoCustomerId, $customerData, $storeId);
        } catch (\Throwable $e) {
            $this->logger->warning('customer_identity.resolve_failed', [
                'correlation_id' => $this->correlationId->get(),
                'magento_customer_id' => $magentoCustomerId,
                'reason' => $e::class,
            ]);

            return new CustomerIdentity(
                $magentoCustomerId,
                isset($local) ? $local->customerId : (string) $magentoCustomerId,
                isset($local) ? $local->customerInternalId : '',
                isset($local) ? $local->customerNumber : '',
                isset($local) ? $local->lastSyncAt : null,
                isset($local) && $local->usesStoredMapping,
                'unverified'
            );
        }
    }

    /** Looks up a remote identity without writing data. */
    public function check(int $magentoCustomerId, ?int $storeId): CustomerIdentity
    {
        $local = $this->storage->get($magentoCustomerId);
        return $this->resolveRemote($local, [], $storeId, false);
    }

    /** Resolves or creates and persists a remote customer. */
    public function sync(int $magentoCustomerId, array $customerData, ?int $storeId): CustomerIdentity
    {
        $local = $this->storage->get($magentoCustomerId);
        $this->storage->assertCustomerIdAvailable($local->customerId, $magentoCustomerId);
        $resolved = $this->resolveRemote($local, $customerData, $storeId, true);
        $this->storage->saveResolved($resolved);
        return $resolved;
    }

    public function recordCustomerNumber(int $magentoCustomerId, string $customerNumber): void
    {
        $this->storage->saveCustomerNumber($magentoCustomerId, $customerNumber);
    }

    /** @return int[] */
    public function getAllCustomerIds(): array
    {
        return $this->storage->getAllCustomerIds();
    }

    /** Builds the remote customer payload. */
    private function resolveRemote(
        CustomerIdentity $local,
        array $customerData,
        ?int $storeId,
        bool $allowCreate
    ): CustomerIdentity {
        $securityToken = $this->securityToken($storeId);
        $headers = $storeId === null ? [] : ['store_id' => $storeId];

        try {
            // Use the ERP-facing CustomerId to avoid resolving a stale internal ID.
            $customer = $this->lookup($securityToken, $local->customerId, $headers);
        } catch (SoapFaultException $e) {
            if (!$allowCreate || !$e->isNotFound()) {
                throw $e;
            }
            $customer = $this->createCustomer(
                $securityToken,
                $local->customerId,
                $customerData,
                $headers
            );
        }

        $remoteCustomerId = trim((string) ($customer['CustomerId'] ?? ''));
        $internalId = trim((string) ($customer['CustomerInternalId'] ?? ''));
        if ($remoteCustomerId === '' || $internalId === '') {
            throw new RuntimeException('EBizCharge customer response is malformed.');
        }
        if ($remoteCustomerId !== $local->customerId) {
            throw new RuntimeException('EBizCharge customer response does not match the requested customer.');
        }

        $tokenResponse = $this->soap->call('GetCustomerToken', [
            'securityToken' => $securityToken,
            'CustomerId' => $remoteCustomerId,
            'customerInternalId' => $internalId,
        ], $headers);
        $customerNumber = $this->scalarResult($tokenResponse, 'GetCustomerTokenResult');
        if ($customerNumber === '0') {
            throw new RuntimeException('EBizCharge returned an invalid customer number.');
        }

        return new CustomerIdentity(
            $local->magentoCustomerId,
            $remoteCustomerId,
            $internalId,
            $customerNumber,
            gmdate('Y-m-d H:i:s'),
            $local->usesStoredMapping,
            'verified'
        );
    }

    /** Loads a remote customer. */
    private function lookup(
        array $securityToken,
        string $customerId,
        array $headers
    ): array {
        return $this->soap->call('GetCustomer', [
            'securityToken' => $securityToken,
            'customerId' => $customerId,
            'customerInternalId' => '',
        ], $headers);
    }

    /** Creates a remote customer. */
    private function createCustomer(
        array $securityToken,
        string $customerId,
        array $customerData,
        array $headers
    ): array {
        $customerData['CustomerId'] = $customerId;
        $customerData['SoftwareId'] = $this->config->getSoftwareTag(
            isset($headers['store_id']) ? (int) $headers['store_id'] : null
        );
        $customer = array_filter(
            $customerData,
            static fn (mixed $value): bool => $value !== '' && $value !== [] && $value !== null
        );

        try {
            $response = $this->soap->call('AddCustomer', [
                'securityToken' => $securityToken,
                'customer' => $customer,
            ], $headers);
        } catch (SoapFaultException $e) {
            if (!$e->isDuplicate()) {
                throw $e;
            }
            return $this->lookup($securityToken, $customerId, $headers);
        }

        if (strcasecmp(trim((string) ($response['Status'] ?? '')), 'Success') === 0) {
            return $response;
        }
        if ($this->isDuplicateResponse($response)) {
            return $this->lookup($securityToken, $customerId, $headers);
        }

        throw new RuntimeException('EBizCharge customer could not be created.');
    }

    /** @param array<string,mixed> $response */
    private function isDuplicateResponse(array $response): bool
    {
        if ((string) ($response['ErrorCode'] ?? '') === '2') {
            return true;
        }
        $message = strtolower((string) ($response['Error'] ?? ''));
        return str_contains($message, 'already exists')
            || str_contains($message, 'record exists')
            || str_contains($message, 'duplicate');
    }

    /** @param array<string,mixed> $response */
    private function scalarResult(array $response, string $key): string
    {
        $value = $response[$key] ?? null;
        if (!is_scalar($value) || trim((string) $value) === '') {
            throw new RuntimeException('EBizCharge customer-token response is malformed.');
        }
        return trim((string) $value);
    }

    /** @return array{UserId:string,SecurityId:string,Password:string} */
    private function securityToken(?int $storeId): array
    {
        return [
            'UserId' => $this->config->getUserId($storeId),
            'SecurityId' => $this->config->getSecurityId($storeId),
            'Password' => $this->config->getPassword($storeId),
        ];
    }
}
