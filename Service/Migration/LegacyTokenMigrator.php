<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service\Migration;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Service\CustomerIdentityManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Soap\ClientFactory;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

/**
 * Migrates rows from the legacy `ebizcharge_token` table (mage_cust_id ↔ ebzc_cust_id) to
 * Magento_Vault's `vault_payment_token` table — one vault token per saved EBizCharge payment method.
 *
 * For each legacy row:
 *  1. Calls EBizCharge `getCustomerPaymentMethods` to fetch every saved method.
 *  2. Creates one `vault_payment_token` per credit-card method, with gateway_token = "<custNum>:<methodId>".
 *  3. Skips ACH methods (Phase 4 will handle those separately).
 *  4. Deduplicates against any token already migrated (by gateway_token + customer_id).
 *
 * Both dry-run and execute modes are supported. Dry-run reads only; execute writes.
 */
class LegacyTokenMigrator
{
    public const SKIP_NO_LEGACY_TABLE = 'no_legacy_table';
    public const SKIP_DUPLICATE = 'duplicate';
    public const SKIP_UNKNOWN_TYPE = 'unknown_type';
    public const SKIP_NO_REMOTE_METHODS = 'no_remote_methods';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Config $config,
        private readonly ClientFactory $clientFactory,
        private readonly PaymentTokenFactoryInterface $tokenFactory,
        private readonly PaymentTokenRepositoryInterface $tokenRepository,
        private readonly PaymentTokenManagementInterface $tokenManagement,
        private readonly CustomerIdentityManager $identityManager,
        private readonly Logger $logger
    ) {
    }

    /**
     * @return array{
     *   processed:int,migrated:int,skipped:array<string,int>,failed:int,errors:array<int,string>
     * }
     */
    public function migrate(bool $dryRun = true): array
    {
        $stats = [
            'processed' => 0,
            'migrated_cards' => 0,
            'migrated_ach' => 0,
            'skipped' => [
                self::SKIP_NO_LEGACY_TABLE => 0,
                self::SKIP_DUPLICATE => 0,
                self::SKIP_UNKNOWN_TYPE => 0,
                self::SKIP_NO_REMOTE_METHODS => 0,
            ],
            'failed' => 0,
            'errors' => [],
        ];

        $legacyRows = $this->fetchLegacyRows();
        if ($legacyRows === null) {
            $stats['skipped'][self::SKIP_NO_LEGACY_TABLE]++;
            return $stats;
        }

        $soapClient = $this->buildSoapClient();
        if ($soapClient === null) {
            $stats['errors'][] = 'Cannot reach EBizCharge — gateway client could not be built. Check credentials.';
            return $stats;
        }

        foreach ($legacyRows as $row) {
            $stats['processed']++;
            $magentoCustomerId = (int) $row['mage_cust_id'];
            $ebzcCustomerId = (string) $row['ebzc_cust_id'];

            if (!$dryRun) {
                try {
                    $this->identityManager->recordCustomerNumber($magentoCustomerId, $ebzcCustomerId);
                } catch (\Throwable $e) {
                    $this->logger->warning('vault.migration.customer_identity_failed', [
                        'magento_customer_id' => $magentoCustomerId,
                        'reason' => $e::class,
                    ]);
                }
            }

            try {
                $methods = $this->fetchRemoteMethods($soapClient, $ebzcCustomerId);
            } catch (\Throwable $e) {
                $stats['failed']++;
                $stats['errors'][] = sprintf(
                    'Customer %d (ebzc=%s): %s',
                    $magentoCustomerId,
                    $ebzcCustomerId,
                    $e->getMessage()
                );
                continue;
            }

            if ($methods === []) {
                $stats['skipped'][self::SKIP_NO_REMOTE_METHODS]++;
                continue;
            }

            foreach ($methods as $method) {
                $type = $this->detectMethodType($method);
                if ($type === null) {
                    $stats['skipped'][self::SKIP_UNKNOWN_TYPE]++;
                    continue;
                }

                $methodId = (string) ($method['MethodID'] ?? '');
                if ($methodId === '') {
                    continue;
                }

                $gatewayToken = $ebzcCustomerId . ':' . $methodId;
                $expectedMethodCode = $type === 'ach' ? Config::METHOD_CODE_ACH : Config::METHOD_CODE;

                if ($this->tokenAlreadyMigrated($magentoCustomerId, $gatewayToken, $expectedMethodCode)) {
                    $stats['skipped'][self::SKIP_DUPLICATE]++;
                    continue;
                }

                if ($dryRun) {
                    $type === 'ach' ? $stats['migrated_ach']++ : $stats['migrated_cards']++;
                    continue;
                }

                try {
                    if ($type === 'ach') {
                        $this->createAchToken($magentoCustomerId, $gatewayToken, $method);
                        $stats['migrated_ach']++;
                    } else {
                        $this->createCardToken($magentoCustomerId, $gatewayToken, $method);
                        $stats['migrated_cards']++;
                    }
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $stats['errors'][] = sprintf(
                        'Customer %d, method %s (%s): %s',
                        $magentoCustomerId,
                        $methodId,
                        $type,
                        $e->getMessage()
                    );
                }
            }
        }

        return $stats;
    }

    /** @return array<int,array{mage_cust_id:int,ebzc_cust_id:string}>|null */
    private function fetchLegacyRows(): ?array
    {
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('ebizcharge_token');
        if (!$connection->isTableExists($tableName)) {
            return null;
        }

        $select = $connection->select()
            ->from($tableName, ['mage_cust_id', 'ebzc_cust_id'])
            ->where('mage_cust_id IS NOT NULL')
            ->where('ebzc_cust_id IS NOT NULL');

        return $connection->fetchAll($select);
    }

    private function buildSoapClient(): ?\SoapClient
    {
        try {
            $endpoint = $this->config->getEndpointUrl();
            return $this->clientFactory->create($endpoint, [
                'soap_version' => SOAP_1_1,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_BOTH,
                'connection_timeout' => $this->config->getSoapConnectTimeout(),
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                            | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
                    ],
                ]),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('migration.client_init_failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function fetchRemoteMethods(\SoapClient $client, string $ebzcCustomerId): array
    {
        $token = [
            'UserId' => $this->config->getUserId(),
            'SecurityId' => $this->config->getSecurityId(),
            'Password' => $this->config->getPassword(),
        ];

        // Args must be wrapped one level deeper: ext-soap consumes __soapCall args positionally
        // and ignores their keys. ebizcharge_token.ebzc_cust_id is the customerToken.
        $response = $client->__soapCall('GetCustomerPaymentMethodProfiles', [[
            'securityToken' => $token,
            'customerToken' => $ebzcCustomerId,
        ]]);

        $decoded = json_decode((string) json_encode($response), true);
        if (!is_array($decoded)) {
            return [];
        }

        $methods = $decoded['GetCustomerPaymentMethodProfilesResult']['PaymentMethodProfile'] ?? [];

        // ext-soap returns a bare object, not a list, when the array holds a single profile.
        if (isset($methods['MethodID'])) {
            return [$methods];
        }
        return is_array($methods) ? array_values($methods) : [];
    }

    /** @return 'card'|'ach'|null */
    private function detectMethodType(array $method): ?string
    {
        $type = strtolower((string) ($method['MethodType'] ?? ''));
        if ($type === '' || $type === 'creditcard' || $type === 'credit_card') {
            return 'card';
        }
        if ($type === 'ach' || $type === 'check' || $type === 'echeck') {
            return 'ach';
        }
        // Fall back to data-shape inference
        if (!empty($method['CardNumber']) || !empty($method['CardExpiration'])) {
            return 'card';
        }
        if (!empty($method['Account']) || !empty($method['Routing'])) {
            return 'ach';
        }
        return null;
    }

    private function tokenAlreadyMigrated(int $customerId, string $gatewayToken, string $methodCode): bool
    {
        try {
            $existing = $this->tokenManagement->getByGatewayToken(
                $gatewayToken,
                $methodCode,
                $customerId
            );
            return $existing !== null;
        } catch (NoSuchEntityException) {
            return false;
        }
    }

    private function createCardToken(int $customerId, string $gatewayToken, array $method): void
    {
        $expiration = $this->parseExpiration((string) ($method['CardExpiration'] ?? ''));
        if ($expiration === null) {
            throw new \RuntimeException('Card expiration could not be parsed.');
        }

        $details = [
            'type' => (string) ($method['CardType'] ?? ''),
            'maskedCC' => substr((string) ($method['CardNumber'] ?? ''), -4),
            'expirationDate' => sprintf('%02d/%04d', $expiration['month'], $expiration['year']),
        ];

        $token = $this->tokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
        $token->setCustomerId($customerId);
        $token->setPaymentMethodCode(Config::METHOD_CODE);
        $token->setGatewayToken($gatewayToken);
        $token->setTokenDetails((string) json_encode($details));
        $token->setIsActive(true);
        $token->setIsVisible(true);
        $token->setExpiresAt(sprintf(
            '%04d-%02d-%02d 23:59:59',
            $expiration['year'],
            $expiration['month'],
            (int) date('t', strtotime(sprintf('%04d-%02d-01', $expiration['year'], $expiration['month'])))
        ));

        $this->tokenRepository->save($token);
    }

    private function createAchToken(int $customerId, string $gatewayToken, array $method): void
    {
        $accountType = strtolower((string) ($method['AccountType'] ?? 'checking'));
        if ($accountType !== 'checking' && $accountType !== 'savings') {
            $accountType = 'checking';
        }

        $details = [
            'accountType' => ucfirst($accountType),
            'maskedAccount' => substr((string) ($method['Account'] ?? ''), -4),
        ];

        $token = $this->tokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_ACCOUNT);
        $token->setCustomerId($customerId);
        $token->setPaymentMethodCode(Config::METHOD_CODE_ACH);
        $token->setGatewayToken($gatewayToken);
        $token->setTokenDetails((string) json_encode($details));
        $token->setIsActive(true);
        $token->setIsVisible(true);
        // Bank accounts have no expiration; sentinel keeps the token visible in saved-methods.
        $token->setExpiresAt(date('Y-m-d H:i:s', strtotime('+10 years')));

        $this->tokenRepository->save($token);
    }

    /** @return array{month:int,year:int}|null */
    private function parseExpiration(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{4})[-\/]?(\d{2})$/', $raw, $m)) {
            return ['year' => (int) $m[1], 'month' => (int) $m[2]];
        }
        if (preg_match('/^(\d{2})[-\/]?(\d{2,4})$/', $raw, $m)) {
            $year = (int) $m[2];
            if ($year < 100) {
                $year += 2000;
            }
            return ['year' => $year, 'month' => (int) $m[1]];
        }
        return null;
    }
}
