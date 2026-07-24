<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Gateway\Response;

use Gtstudio\Ebizcharge\Gateway\Response\PaymentDetailsHandler;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Service\CorrelationIdProvider;
use Gtstudio\Ebizcharge\Service\CustomerIdentityManager;
use Gtstudio\Ebizcharge\Service\CustomerIdentityMetadata;
use Magento\Framework\DataObject;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;

class PaymentDetailsHandlerTest extends TestCase
{
    public function testResponseCustomerNumberIsAuditedAndPersisted(): void
    {
        $manager = $this->createMock(CustomerIdentityManager::class);
        $manager->expects($this->once())->method('recordCustomerNumber')->with(12, '00042');
        $payment = $this->payment(12);

        $this->handler($manager)->handle(
            ['payment' => $this->paymentDataObject($payment)],
            ['ResultCode' => 'A', 'CustNum' => '00042']
        );

        $this->assertSame(
            '00042',
            $payment->getAdditionalInformation(CustomerIdentityMetadata::CUSTOMER_NUMBER)
        );
    }

    public function testZeroCustomerNumberIsIgnored(): void
    {
        $manager = $this->createMock(CustomerIdentityManager::class);
        $manager->expects($this->never())->method('recordCustomerNumber');
        $payment = $this->payment(12);

        $this->handler($manager)->handle(
            ['payment' => $this->paymentDataObject($payment)],
            ['ResultCode' => 'A', 'CustNum' => '0']
        );

        $this->assertNull($payment->getAdditionalInformation(CustomerIdentityMetadata::CUSTOMER_NUMBER));
    }

    public function testMappingWriteFailureDoesNotFailCompletedTransaction(): void
    {
        $manager = $this->createMock(CustomerIdentityManager::class);
        $manager->method('recordCustomerNumber')->willThrowException(new \RuntimeException('database'));
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('warning')->with(
            'customer_identity.response_persist_failed',
            $this->callback(static fn (array $context): bool => $context['reason'] === \RuntimeException::class)
        );

        $this->handler($manager, $logger)->handle(
            ['payment' => $this->paymentDataObject($this->payment(12))],
            ['ResultCode' => 'A', 'CustNum' => '42']
        );
    }

    private function handler(
        CustomerIdentityManager $manager,
        ?Logger $logger = null
    ): PaymentDetailsHandler {
        $correlation = $this->createMock(CorrelationIdProvider::class);
        $correlation->method('get')->willReturn('cid');
        return new PaymentDetailsHandler(
            $manager,
            $logger ?? $this->createMock(Logger::class),
            $correlation
        );
    }

    private function payment(int $customerId): Payment
    {
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder'])
            ->getMock();
        $payment->method('getOrder')->willReturn(new DataObject(['customer_id' => $customerId]));
        return $payment;
    }

    private function paymentDataObject(Payment $payment): PaymentDataObjectInterface
    {
        return new class ($payment) implements PaymentDataObjectInterface {
            public function __construct(private readonly Payment $payment)
            {
            }

            public function getOrder()
            {
                return null;
            }

            public function getPayment()
            {
                return $this->payment;
            }
        };
    }
}
