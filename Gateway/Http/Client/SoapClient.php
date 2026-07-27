<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Http\Client;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Gateway\Http\ResponseNormalizer;
use Gtstudio\Ebizcharge\Gateway\Http\SoapFaultException;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Service\CorrelationIdProvider;
use Gtstudio\Ebizcharge\Service\DebugTraceRecorder;
use Magento\Framework\Webapi\Soap\ClientFactory;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use SoapFault;

/** Sends secured and redacted EBizCharge SOAP requests. */
class SoapClient implements ClientInterface
{
    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly CorrelationIdProvider $correlationId,
        private readonly DebugTraceRecorder $debugTraceRecorder,
        private readonly ResponseNormalizer $responseNormalizer
    ) {
    }

    public function placeRequest(TransferInterface $transfer): array
    {
        $body = $transfer->getBody();
        $method = (string) ($body['__method'] ?? 'runTransaction');
        unset($body['__method']);
        // Remove request-local metadata before SOAP serialization and tracing.
        unset($body['__vault_profile']);

        $headers = $transfer->getHeaders();
        $storeId = isset($headers['store_id']) ? (int) $headers['store_id'] : null;

        $endpoint = $this->config->getEndpointUrl($storeId);
        $cid = $this->correlationId->get();
        $this->logger->info('soap.request', [
            'correlation_id' => $cid,
            'method' => $method,
            'endpoint' => $endpoint,
            'idempotency_key' => $headers['idempotency_key'] ?? null,
        ]);

        $command = isset($body['tran']['Command']) ? (string) $body['tran']['Command'] : null;
        $start = microtime(true);

        try {
            $options = [
                'soap_version' => SOAP_1_1,
                'trace' => $this->config->isDebugEnabled($storeId) ? 1 : 0,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_BOTH,
                'connection_timeout' => $this->config->getSoapConnectTimeout($storeId),
                // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged -- SOAP TLS context.
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                        'allow_self_signed' => false,
                        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                            | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
                    ],
                    'http' => [
                        'timeout' => $this->config->getSoapReadTimeout($storeId),
                        'user_agent' => 'Gtstudio_Ebizcharge/1.0 Magento2',
                    ],
                ]),
            ];
            $client = $this->clientFactory->create($endpoint, $options);
            $response = $client->__soapCall($method, [$body]);
        } catch (SoapFault $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);
            $this->logger->error('soap.fault', [
                'correlation_id' => $cid,
                'method' => $method,
                'fault_code' => $e->faultcode ?? null,
                'fault_string' => $e->getMessage(),
                'latency_ms' => $latency,
            ]);
            // Keep remote fault text out of database-backed traces.
            $this->captureTrace($cid, $method, $endpoint, $command, $body, [], $latency, 'SOAP fault', $storeId);
            throw new SoapFaultException(
                (string) ($e->faultcode ?? ''),
                $e->getMessage(),
                $e
            );
        } catch (\Throwable $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);
            $this->logger->error('soap.exception', [
                'correlation_id' => $cid,
                'method' => $method,
                'message' => $e->getMessage(),
                'latency_ms' => $latency,
            ]);
            $this->captureTrace(
                $cid,
                $method,
                $endpoint,
                $command,
                $body,
                [],
                $latency,
                'Gateway communication error',
                $storeId
            );
            throw new ClientException(__('Gateway communication error.'));
        }

        $latency = (int) round((microtime(true) - $start) * 1000);
        $decoded = $this->responseNormalizer->normalize($response, $method);

        $this->logger->info('soap.response', [
            'correlation_id' => $cid,
            'method' => $method,
            'result_code' => $decoded['ResultCode'] ?? null,
            'ref_num' => $decoded['RefNum'] ?? null,
            'latency_ms' => $latency,
        ]);

        $this->captureTrace($cid, $method, $endpoint, $command, $body, $decoded, $latency, null, $storeId);

        return $decoded;
    }

    private function captureTrace(
        string $cid,
        string $method,
        string $endpoint,
        ?string $command,
        array $request,
        array $response,
        int $latency,
        ?string $errorMessage,
        ?int $storeId
    ): void {
        if (!$this->debugTraceRecorder->isEnabled($storeId)) {
            return;
        }
        $this->debugTraceRecorder->record(
            $cid,
            $method,
            $endpoint,
            $command,
            $request,
            $response,
            $latency,
            $errorMessage
        );
    }
}
