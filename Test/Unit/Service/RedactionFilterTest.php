<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Service;

use Gtstudio\Ebizcharge\Service\RedactionFilter;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/**
 * Security-critical tests. The redaction filter is the last line of defense before PAN/CVV/ACH
 * data hits the log file or any other Monolog handler. Every regression here is a potential
 * PCI incident.
 */
class RedactionFilterTest extends TestCase
{
    private RedactionFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new RedactionFilter();
    }

    public function testRedactsValidLuhnPanInMessage(): void
    {
        $record = $this->record('Charging card 4111111111111111 for 50.00');
        $out = ($this->filter)($record);
        $this->assertStringNotContainsString('4111111111111111', $out->message);
        $this->assertStringContainsString('411111', $out->message);
        $this->assertStringContainsString('1111', $out->message);
    }

    public function testDoesNotRedactNonLuhnDigits(): void
    {
        $record = $this->record('Order 1234567890123456 (random number)');
        $out = ($this->filter)($record);
        $this->assertStringContainsString('1234567890123456', $out->message);
    }

    public function testRedactsCvvKeyValue(): void
    {
        $record = $this->record('cvv2: 123', context: ['cvv2' => '999']);
        $out = ($this->filter)($record);
        $this->assertStringNotContainsString('123', $out->message);
        $this->assertStringContainsString('***REDACTED***', $out->message);
    }

    public function testRedactsCardCodeKeyValue(): void
    {
        $record = $this->record('CardCode: 4321');
        $out = ($this->filter)($record);
        $this->assertStringNotContainsString('4321', $out->message);
    }

    public function testRedactsAchAccountNumberByKey(): void
    {
        $record = $this->record('account: 12345678901');
        $out = ($this->filter)($record);
        $this->assertStringNotContainsString('12345678901', $out->message);
    }

    public function testRedactsAchRoutingByKey(): void
    {
        $record = $this->record('routing: 011000015');
        $out = ($this->filter)($record);
        $this->assertStringNotContainsString('011000015', $out->message);
    }

    public function testRedactsTrackData(): void
    {
        $record = $this->record('Swipe: %B4111111111111111^DOE/JOHN^25121011000000000000?');
        $out = ($this->filter)($record);
        $this->assertStringNotContainsString('4111111111111111', $out->message);
        $this->assertStringNotContainsString('DOE/JOHN', $out->message);
    }

    public function testRedactsNestedContext(): void
    {
        $record = $this->record(
            'soap.request',
            context: [
                'method' => 'runTransaction',
                'body' => [
                    'CardNumber' => '5555555555554444',
                    'cvv2' => '123',
                ],
            ]
        );
        $out = ($this->filter)($record);
        $this->assertSame('5555555555554444', '5555555555554444', 'sanity');
        $body = $out->context['body'] ?? [];
        $this->assertIsArray($body);
        $this->assertStringNotContainsString('5555555555554444', json_encode($out->context));
    }

    public function testNonStringValuesPassThroughUntouched(): void
    {
        $record = $this->record('latency', context: ['latency_ms' => 42, 'success' => true]);
        $out = ($this->filter)($record);
        $this->assertSame(42, $out->context['latency_ms']);
        $this->assertTrue($out->context['success']);
    }

    public function testEmptyRecordIsFine(): void
    {
        $record = $this->record('');
        $out = ($this->filter)($record);
        $this->assertSame('', $out->message);
    }

    private function record(string $message, array $context = [], array $extra = []): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'gtstudio_ebizcharge',
            level: Level::Info,
            message: $message,
            context: $context,
            extra: $extra
        );
    }
}
