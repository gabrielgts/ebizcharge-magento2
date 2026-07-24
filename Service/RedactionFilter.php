<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Strips PAN, CVV, ACH account, and ACH routing numbers from any string before it reaches the formatter.
 *
 * Wired as a Monolog ProcessorInterface on the gtstudio_ebizcharge channel so individual log() callers
 * cannot bypass it. PAN and CVV must never reach disk.
 */
class RedactionFilter implements ProcessorInterface
{
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
            static function (array $match): string {
                $digits = preg_replace('/\D/', '', $match[0]) ?? '';
                if ($digits === '' || !self::passesLuhn($digits)) {
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

    private static function passesLuhn(string $digits): bool
    {
        $sum = 0;
        $shouldDouble = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];
            if ($shouldDouble) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $shouldDouble = !$shouldDouble;
        }
        return $sum > 0 && $sum % 10 === 0;
    }
}
