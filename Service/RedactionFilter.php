<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/** Redacts payment data and gateway credentials from log records. */
class RedactionFilter implements ProcessorInterface
{
    private const SENSITIVE_KEYS = [
        'CardNumber',
        'CardCode',
        'cc_number',
        'cc_cid',
        'CVV',
        'CVV2',
        'Account',
        'Routing',
        'ach_account',
        'ach_routing',
        'Password',
        'SecurityId',
        'UserId',
    ];

    private const PAN_PATTERN = '/\b(?:\d[ -]*?){13,19}\b/';
    private const CVV_PATTERN = '/(?<=\bcvv2?["\':=>\s]{1,5})\d{3,4}\b/i';
    private const CARDCODE_PATTERN = '/(?<=\bcardcode["\':=>\s]{1,5})\d{3,4}\b/i';
    private const ROUTING_PATTERN = '/(?<=\b(?:routing|achroute)["\':=>\s]{1,5})\d{6,9}\b/i';
    private const ACCOUNT_PATTERN = '/(?<=\b(?:account|achaccount)["\':=>\s]{1,5})\d{4,17}\b/i';
    private const TRACK_PATTERN = '/%[Bb]\d{13,19}\^[^\^]+\^\d+\?/';

    private const REDACTION = '***REDACTED***';

    public function __invoke(LogRecord $record): LogRecord
    {
        $message = $this->redact($record->message);
        $context = $this->redactArray($record->context);
        $extra = $this->redactArray($record->extra);

        return $record->with(message: $message, context: $context, extra: $extra);
    }

    private function redact(string $value): string
    {
        $value = (string) preg_replace(self::TRACK_PATTERN, self::REDACTION, $value);
        $value = (string) preg_replace(self::CVV_PATTERN, self::REDACTION, $value);
        $value = (string) preg_replace(self::CARDCODE_PATTERN, self::REDACTION, $value);
        $value = (string) preg_replace(self::ROUTING_PATTERN, self::REDACTION, $value);
        $value = (string) preg_replace(self::ACCOUNT_PATTERN, self::REDACTION, $value);

        return (string) preg_replace_callback(
            self::PAN_PATTERN,
            function (array $match): string {
                $digits = preg_replace('/\D/', '', $match[0]) ?? '';
                if ($digits === '' || !$this->passesLuhn($digits)) {
                    return $match[0];
                }
                return substr($digits, 0, 6) . str_repeat('*', max(0, strlen($digits) - 10)) . substr($digits, -4);
            },
            $value
        );
    }

    private function redactArray(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $result[$key] = self::REDACTION;
                continue;
            }
            if (is_array($value)) {
                $result[$key] = $this->redactArray($value);
                continue;
            }
            if (is_string($value)) {
                $result[$key] = $this->redact($value);
                continue;
            }
            $result[$key] = $value;
        }
        return $result;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (strcasecmp($key, $sensitiveKey) === 0) {
                return true;
            }
        }

        return false;
    }

    private function passesLuhn(string $digits): bool
    {
        $sum = 0;
        $shouldDouble = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];
            if ($shouldDouble) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $shouldDouble = !$shouldDouble;
        }
        return $sum > 0 && $sum % 10 === 0;
    }
}
