<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Gateway\Request;

use Gtstudio\Ebizcharge\Gateway\Request\CustomerDataBuilder;
use Gtstudio\Ebizcharge\Service\CustomerIdentity;
use Gtstudio\Ebizcharge\Service\CustomerIdentityManager;
use Gtstudio\Ebizcharge\Service\CustomerIdentityMetadata;
use Gtstudio\Ebizcharge\Service\CustomerPayloadBuilder;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use PHPUnit\Framework\TestCase;

class CustomerDataBuilderTest extends TestCase
{
    public function testMappedCustomerIdIsSentAndAudited(): void
    {
        $order = $this->createMock(OrderAdapterInterface::class);
        $order->method('getCustomerId')->willReturn(12);
        $order->method('getStoreId')->willReturn(3);
        $identity = new CustomerIdentity(12, 'ERP-12', 'internal-12', '00042', null, true, 'verified');
        $manager = $this->createMock(CustomerIdentityManager::class);
        $manager->expects($this->once())->method('resolveForTransaction')->willReturn($identity);
        $payload = $this->createMock(CustomerPayloadBuilder::class);
        $payload->method('fromOrder')->with($order)->willReturn(['FirstName' => 'Ada']);
        $payment = $this->createMock(InfoInterface::class);
        $metadata = [];
        $payment->expects($this->exactly(4))
            ->method('setAdditionalInformation')
            ->willReturnCallback(static function (string $key, mixed $value) use (&$metadata, $payment) {
                $metadata[$key] = $value;
                return $payment;
            });

        $result = (new CustomerDataBuilder($manager, $payload))->build([
            'payment' => $this->paymentDataObject($order, $payment),
        ]);

        $this->assertSame('ERP-12', $result['tran']['CustomerID']);
        $this->assertSame('ERP-12', $metadata[CustomerIdentityMetadata::CUSTOMER_ID]);
        $this->assertSame('internal-12', $metadata[CustomerIdentityMetadata::CUSTOMER_INTERNAL_ID]);
        $this->assertSame('00042', $metadata[CustomerIdentityMetadata::CUSTOMER_NUMBER]);
        $this->assertSame('verified', $metadata[CustomerIdentityMetadata::STATUS]);
    }

    public function testGuestUsesGuestWithoutProvisioning(): void
    {
        $order = $this->createMock(OrderAdapterInterface::class);
        $order->method('getCustomerId')->willReturn(null);
        $manager = $this->createMock(CustomerIdentityManager::class);
        $manager->expects($this->never())->method('resolveForTransaction');

        $result = (new CustomerDataBuilder(
            $manager,
            $this->createMock(CustomerPayloadBuilder::class)
        ))->build([
            'payment' => $this->paymentDataObject($order, $this->createMock(InfoInterface::class)),
        ]);

        $this->assertSame('Guest', $result['tran']['CustomerID']);
    }

    private function paymentDataObject(
        OrderAdapterInterface $order,
        InfoInterface $payment
    ): PaymentDataObjectInterface {
        return new class ($order, $payment) implements PaymentDataObjectInterface {
            public function __construct(
                private readonly OrderAdapterInterface $order,
                private readonly InfoInterface $payment
            ) {
            }

            public function getOrder()
            {
                return $this->order;
            }

            public function getPayment()
            {
                return $this->payment;
            }
        };
    }
}
