<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Model\DebugTraceFactory;
use Gtstudio\Ebizcharge\Model\ResourceModel\DebugTrace as DebugTraceResource;

/**
 * Captures one SOAP request/response cycle into the debug-trace table when Debug Mode is on.
 *
 * Two layers of PCI redaction protect against accidental leakage:
 *   1. The Monolog RedactionFilter is already a processor on the gtstudio_ebizcharge channel
 *      and would redact anything we logged.
 *   2. This recorder strips PAN/CVV/account/routing keys from the JSON body before persisting
 *      so they NEVER reach the database, even if the filter somehow misses a string match.
 *
 * Persistence failures are swallowed (and logged) — debug capture must never break a payment.
 */
class DebugTraceRecorder
{
    private const SENSITIVE_KEYS = [
        'CardNumber',
        'CardCode',
        'cc_number',
        'cc_cid',
        'Account',
        'Routing',
        'ach_account',
        'ach_routing',
        'Password',
        'SecurityId',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly DebugTraceFactory $traceFactory,
        private readonly DebugTraceResource $resource,
        private readonly Logger $logger
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->config->isDebugEnabled($storeId);
    }

    /**
     * @param array<string,mixed> $request
     * @param array<string,mixed> $response
     */
    public function record(
        string $correlationId,
        string $soapMethod,
        string $endpoint,
        ?string $command,
        array $request,
        array $response,
        int $latencyMs,
        ?string $errorMessage = null
    ): void {
        try {
            $trace = $this->traceFactory->create();
            $trace->setData([
                'correlation_id' => $correlationId,
                'soap_method' => $soapMethod,
                'command' => $command,
                'endpoint' => $endpoint,
                'request_summary' => $this->encode($this->redact($request)),
                'response_summary' => $this->encode($this->redact($response)),
                'result_code' => isset($response['ResultCode']) ? (string) $response['ResultCode'] : null,
                'ref_num' => isset($response['RefNum']) ? (string) $response['RefNum'] : null,
                'latency_ms' => $latencyMs,
                'error_message' => $errorMessage !== null ? substr($errorMessage, 0, 512) : null,
            ]);
            $this->resource->save($trace);
        } catch (\Throwable $e) {
            $this->logger->warning('debug_trace.record_failed', [
                'correlation_id' => $correlationId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /** @param array<string,mixed> $payload */
    private function redact(array $payload): array
    {
        $out = [];
        foreach ($payload as $key => $value) {
            if (in_array((string) $key, self::SENSITIVE_KEYS, true)) {
                $out[$key] = '***REDACTED***';
                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->redact($value);
                continue;
            }
            $out[$key] = $value;
        }
        return $out;
    }

    /** @param array<string,mixed> $payload */
    private function encode(array $payload): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($encoded === false) {
            return '{"_encode_error":true}';
        }
        if (strlen($encoded) > 16000) {
            $encoded = substr($encoded, 0, 16000) . '... [truncated]';
        }
        return $encoded;
    }
}
