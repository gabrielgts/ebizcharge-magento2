<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service\Gateway;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Logger\Logger;
use Magento\Framework\Webapi\Soap\ClientFactory;

/** Probes configured or supplied gateway credentials. */
class ConnectionProbe
{
    public function __construct(
        private readonly Config $config,
        private readonly ClientFactory $clientFactory,
        private readonly Logger $logger
    ) {
    }

    /** @return array{success:bool,latency_ms:int,message:string} */
    public function probe(
        ?string $userIdOverride = null,
        ?string $securityIdOverride = null,
        ?string $passwordOverride = null,
        ?string $endpointModeOverride = null,
        ?string $endpointUrlOverride = null
    ): array {
        $userId = $userIdOverride !== null && $userIdOverride !== ''
            ? $userIdOverride
            : $this->config->getUserId();
        $securityId = $securityIdOverride !== null && $securityIdOverride !== ''
            ? $securityIdOverride
            : $this->config->getSecurityId();
        $password = $passwordOverride !== null && $passwordOverride !== ''
            ? $passwordOverride
            : $this->config->getPassword();

        if ($userId === '' || $securityId === '' || $password === '') {
            return [
                'success' => false,
                'latency_ms' => 0,
                'message' => 'Credentials are empty.',
            ];
        }

        $endpoint = $this->resolveEndpoint($endpointModeOverride, $endpointUrlOverride);
        $start = microtime(true);

        try {
            $client = $this->clientFactory->create($endpoint, [
                'soap_version' => SOAP_1_1,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_BOTH,
                'connection_timeout' => $this->config->getSoapConnectTimeout(),
                // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged -- SOAP TLS context.
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                            | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
                    ],
                ]),
            ]);
            // ext-soap requires the parameter struct as the first positional argument.
            $client->__soapCall('GetMerchantIntegrationSettings', [[
                'securityToken' => [
                    'SecurityId' => $securityId,
                    'UserId' => $userId,
                    'Password' => $password,
                ],
            ]]);
            $latency = (int) round((microtime(true) - $start) * 1000);
            return ['success' => true, 'latency_ms' => $latency, 'message' => 'Connected'];
        } catch (\SoapFault $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);
            $this->logger->info('probe.soap_fault', [
                'fault_code' => $e->faultcode ?? null,
                'message' => $e->getMessage(),
                'latency_ms' => $latency,
            ]);
            return [
                'success' => false,
                'latency_ms' => $latency,
                'message' => $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);
            $this->logger->info('probe.exception', [
                'message' => $e->getMessage(),
                'latency_ms' => $latency,
            ]);
            return [
                'success' => false,
                'latency_ms' => $latency,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function resolveEndpoint(?string $modeOverride, ?string $urlOverride): string
    {
        if ($urlOverride !== null && trim($urlOverride) !== '') {
            return trim($urlOverride);
        }
        if ($modeOverride === Config::ENDPOINT_SANDBOX) {
            return Config::URL_SANDBOX;
        }
        if ($modeOverride === Config::ENDPOINT_PRODUCTION) {
            return Config::URL_PRODUCTION;
        }
        return $this->config->getEndpointUrl();
    }
}
