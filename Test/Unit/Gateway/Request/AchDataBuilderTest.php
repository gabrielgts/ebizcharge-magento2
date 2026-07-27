<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Gateway\Request;

use Gtstudio\Ebizcharge\Gateway\Request\AchDataBuilder;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use PHPUnit\Framework\TestCase;

/** Verifies secure ACH request construction. */
class AchDataBuilderTest extends TestCase
{
    private AchDataBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new AchDataBuilder();
    }

    public function testEmitsCheckDataAndScrubsRawValuesOffPayment(): void
    {
        $payment = $this->makePayment([
            'ach_account' => '12345678901',
            'ach_routing' => '021000322',
            'ach_type' => 'checking',
        ]);

        $removed = [];
        $payment->expects($this->exactly(2))
            ->method('unsAdditionalInformation')
            ->willReturnCallback(
                function (string $key) use (&$removed, $payment) {
                    $removed[] = $key;
                    return $payment;
                }
            );

        $additionalSet = [];
        $payment->method('setAdditionalInformation')->willReturnCallback(
            function (string $key, $value) use (&$additionalSet, $payment) {
                $additionalSet[$key] = $value;
                return $payment;
            }
        );

        $result = $this->builder->build(['payment' => $this->wrapPayment($payment)]);

        $this->assertArrayHasKey('tran', $result);
        $this->assertArrayHasKey('CheckData', $result['tran']);
        $this->assertSame('12345678901', $result['tran']['CheckData']['Account']);
        $this->assertSame('021000322', $result['tran']['CheckData']['Routing']);
        $this->assertSame('checking', $result['tran']['CheckData']['AccountType']);
        $this->assertSame(['ach_account', 'ach_routing'], $removed);

        // Persistence: only masked values remain
        $this->assertSame('8901', $additionalSet['ach_last4'] ?? null);
        $this->assertSame('021000XXX', $additionalSet['ach_routing_masked'] ?? null);
    }

    public function testInvalidAccountTypeFallsBackToChecking(): void
    {
        $payment = $this->makePayment([
            'ach_account' => '999',
            'ach_routing' => '021000322',
            'ach_type' => 'mortgage',
        ]);
        $result = $this->builder->build(['payment' => $this->wrapPayment($payment)]);
        $this->assertSame('checking', $result['tran']['CheckData']['AccountType']);
    }

    public function testEmptyPaymentReturnsNoCheckData(): void
    {
        $payment = $this->makePayment([]);
        $result = $this->builder->build(['payment' => $this->wrapPayment($payment)]);
        $this->assertSame([], $result['tran'] ?? []);
    }

    public function testShortAccountStillMasksLast4(): void
    {
        $payment = $this->makePayment([
            'ach_account' => '42',
            'ach_routing' => '021000322',
            'ach_type' => 'savings',
        ]);
        $additionalSet = [];
        $payment->method('setAdditionalInformation')->willReturnCallback(
            function (string $key, $value) use (&$additionalSet, $payment) {
                $additionalSet[$key] = $value;
                return $payment;
            }
        );
        $this->builder->build(['payment' => $this->wrapPayment($payment)]);
        $this->assertSame('42', $additionalSet['ach_last4']);
    }

    private function makePayment(array $additionalInformation): OrderPaymentInterface
    {
        $payment = $this->getMockBuilder(OrderPaymentInterface::class)
            ->onlyMethods(get_class_methods(OrderPaymentInterface::class))
            ->addMethods(['unsAdditionalInformation'])
            ->getMock();
        $payment->method('getAdditionalInformation')->willReturnCallback(
            fn ($key = null) => $key === null ? $additionalInformation : ($additionalInformation[$key] ?? null)
        );
        $payment->method('unsAdditionalInformation')->willReturnSelf();
        return $payment;
    }

    private function wrapPayment(OrderPaymentInterface $payment): PaymentDataObjectInterface
    {
        $do = $this->createMock(PaymentDataObjectInterface::class);
        $do->method('getPayment')->willReturn($payment);
        return $do;
    }
}
