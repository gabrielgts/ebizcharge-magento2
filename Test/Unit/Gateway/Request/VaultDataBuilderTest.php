<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Gateway\Request;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Gateway\Request\VaultDataBuilder;
use Gtstudio\Ebizcharge\Service\CustomerIdentity;
use Gtstudio\Ebizcharge\Service\CustomerIdentityManager;
use Magento\Framework\Api\ExtensionAttributesInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VaultDataBuilderTest extends TestCase
{
    public function testBuildsExactSavedCardContractWithoutPanOrCvv(): void
    {
        $result = $this->builder()->build([
            'payment' => $this->paymentDataObject($this->payment('00042:007', 123, 123)),
        ]);

        $this->assertSame('00042', $result['custNum']);
        $this->assertSame('007', $result['paymentMethodID']);
        $this->assertSame('runCustomerTransaction', $result['__method']);
        $this->assertFalse($result['tran']['isRecurring']);
        $this->assertSame('', $result['tran']['InventoryLocation']);
        $this->assertFalse($result['tran']['IgnoreDuplicate']);
        $this->assertArrayNotHasKey('CardNumber', $result['tran']);
        $this->assertArrayNotHasKey('CardCode', $result['tran']);
        $this->assertArrayNotHasKey('CreditCardData', $result['tran']);
    }

    public function testRejectsMalformedGatewayTokenBeforeSoap(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('saved card information is invalid');

        $this->builder()->build([
            'payment' => $this->paymentDataObject($this->payment('not-a-profile', 123, 123)),
        ]);
    }

    public function testMarksSyntheticSubscriptionChargeAsRecurring(): void
    {
        $result = $this->builder()->build([
            'payment' => $this->paymentDataObject($this->payment('42:7', 123, 123, true, true)),
        ]);

        $this->assertTrue($result['tran']['isRecurring']);
    }

    public function testRejectsTokenOwnedByAnotherCustomer(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('not available for this customer');

        $this->builder()->build([
            'payment' => $this->paymentDataObject($this->payment('42:7', 999, 123)),
        ]);
    }

    public function testRejectsTokenWithoutCustomerOwnership(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('not available for this customer');

        $this->builder()->build([
            'payment' => $this->paymentDataObject($this->payment('42:7', 0, 123)),
        ]);
    }

    public function testRejectsInactiveToken(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('no longer available');

        $this->builder()->build([
            'payment' => $this->paymentDataObject($this->payment('42:7', 123, 123, false)),
        ]);
    }

    private function builder(): VaultDataBuilder
    {
        $identityManager = $this->createMock(CustomerIdentityManager::class);
        $identityManager->method('getLocal')->willReturn(
            new CustomerIdentity(123, 'ERP-123', 'internal-123', '00042', null, true, 'cached')
        );
        return new VaultDataBuilder($identityManager);
    }

    /** @return OrderPaymentInterface&MockObject */
    private function payment(
        string $gatewayToken,
        int $tokenCustomerId,
        int $orderCustomerId,
        bool $active = true,
        bool $recurring = false
    ): OrderPaymentInterface {
        $token = $this->createMock(PaymentTokenInterface::class);
        $token->method('getGatewayToken')->willReturn($gatewayToken);
        $token->method('getCustomerId')->willReturn($tokenCustomerId);
        $token->method('getIsActive')->willReturn($active);
        $token->method('getPaymentMethodCode')->willReturn(Config::METHOD_CODE);

        $extension = $this->getMockBuilder(ExtensionAttributesInterface::class)
            ->addMethods(['getVaultPaymentToken'])
            ->getMock();
        $extension->method('getVaultPaymentToken')->willReturn($token);

        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getExtensionAttributes', 'getOrder'])
            ->getMock();
        $payment->method('getExtensionAttributes')->willReturn($extension);
        $payment->method('getOrder')->willReturn(new DataObject(['customer_id' => $orderCustomerId]));
        $payment->setData('additional_information', ['gtstudio_recurring_charge' => $recurring]);
        return $payment;
    }

    private function paymentDataObject(OrderPaymentInterface $payment): PaymentDataObjectInterface
    {
        return new class ($payment) implements PaymentDataObjectInterface {
            public function __construct(private readonly OrderPaymentInterface $payment)
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
