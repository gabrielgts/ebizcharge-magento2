<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Gateway\Response;

use Gtstudio\Ebizcharge\Gateway\Response\VaultDetailsHandler;
use Gtstudio\Ebizcharge\Logger\Logger;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Sales\Model\Order\Payment;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use PHPUnit\Framework\TestCase;

class VaultDetailsHandlerTest extends TestCase
{
    public function testCreatesMaskedMagentoCardTokenOnly(): void
    {
        $token = $this->createMock(PaymentTokenInterface::class);
        $token->expects($this->once())->method('setGatewayToken')->with('00042:007');
        $token->expects($this->once())->method('setTokenDetails')->with($this->callback(
            static function (string $json): bool {
                $details = json_decode($json, true);
                return $details === [
                    'type' => 'VI',
                    'maskedCC' => '1111',
                    'expirationDate' => '06/2030',
                ] && !str_contains($json, '4111111111111111') && !str_contains($json, '123');
            }
        ));
        $token->expects($this->once())->method('setExpiresAt')->with('2030-06-30 23:59:59');
        $token->method('getType')->willReturn(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
        $token->method('getExpiresAt')->willReturn('2030-06-30 23:59:59');

        $tokenFactory = $this->createMock(PaymentTokenFactoryInterface::class);
        $tokenFactory->expects($this->once())->method('create')
            ->with(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD)
            ->willReturn($token);
        $extension = $this->createMock(OrderPaymentExtensionInterface::class);
        $extension->expects($this->once())->method('setVaultPaymentToken')->with($token);
        $payment = $this->payment($extension);
        $payment->expects($this->once())->method('setExtensionAttributes')->with($extension);

        $this->handler($tokenFactory)->handle(
            ['payment' => $this->paymentDataObject($payment)],
            ['ResultCode' => 'A', 'CustNum' => '00042', 'PaymentMethodID' => '007']
        );
    }

    public function testProvisioningFailurePersistsStatusButCreatesNoToken(): void
    {
        $tokenFactory = $this->createMock(PaymentTokenFactoryInterface::class);
        $tokenFactory->expects($this->never())->method('create');
        $payment = $this->payment(null);

        $this->handler($tokenFactory)->handle(
            ['payment' => $this->paymentDataObject($payment)],
            ['ResultCode' => 'A', 'VaultSaveStatus' => 'failed']
        );

        $this->assertSame('failed', $payment->getAdditionalInformation('vault_save_status'));
    }

    private function handler(PaymentTokenFactoryInterface $tokenFactory): VaultDetailsHandler
    {
        return new VaultDetailsHandler(
            $tokenFactory,
            $this->createMock(OrderPaymentExtensionInterfaceFactory::class),
            $this->createMock(Logger::class)
        );
    }

    private function payment(?OrderPaymentExtensionInterface $extension): Payment
    {
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getExtensionAttributes', 'setExtensionAttributes'])
            ->getMock();
        $payment->method('getExtensionAttributes')->willReturn($extension);
        $payment->setData([
            'method' => 'gtstudio_ebizcharge',
            'cc_type' => 'VI',
            'cc_last_4' => '1111',
            'cc_exp_month' => '6',
            'cc_exp_year' => '2030',
            'additional_information' => [VaultConfigProvider::IS_ACTIVE_CODE => true],
        ]);
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
