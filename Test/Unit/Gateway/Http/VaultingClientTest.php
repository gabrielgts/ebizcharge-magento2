<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Gateway\Http;

use Gtstudio\Ebizcharge\Gateway\Http\Client\SoapClient;
use Gtstudio\Ebizcharge\Gateway\Http\Client\VaultingClient;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Service\CardProfileProvisioner;
use Gtstudio\Ebizcharge\Service\CorrelationIdProvider;
use Magento\Payment\Gateway\Http\TransferInterface;
use PHPUnit\Framework\TestCase;

class VaultingClientTest extends TestCase
{
    public function testApprovedTransactionIsEnrichedWithProvisionedIdentifiers(): void
    {
        $transaction = $this->createMock(SoapClient::class);
        $transaction->method('placeRequest')->willReturn(['ResultCode' => 'A', 'RefNum' => '123']);
        $provisioner = $this->createMock(CardProfileProvisioner::class);
        $provisioner->expects($this->once())->method('provision')->willReturn([
            'cust_num' => '00042',
            'method_id' => '99',
        ]);

        $result = (new VaultingClient(
            $transaction,
            $provisioner,
            $this->createMock(Logger::class),
            $this->correlationId()
        ))
            ->placeRequest($this->transfer(true));

        $this->assertSame('A', $result['ResultCode']);
        $this->assertSame('00042', $result['CustNum']);
        $this->assertSame('99', $result['PaymentMethodID']);
        $this->assertSame('saved', $result['VaultSaveStatus']);
    }

    public function testDeclineNeverProvisionsProfile(): void
    {
        $transaction = $this->createMock(SoapClient::class);
        $transaction->method('placeRequest')->willReturn(['ResultCode' => 'D']);
        $provisioner = $this->createMock(CardProfileProvisioner::class);
        $provisioner->expects($this->never())->method('provision');

        $result = (new VaultingClient(
            $transaction,
            $provisioner,
            $this->createMock(Logger::class),
            $this->correlationId()
        ))
            ->placeRequest($this->transfer(true));

        $this->assertSame(['ResultCode' => 'D'], $result);
    }

    public function testProfileFailurePreservesApprovedPayment(): void
    {
        $transaction = $this->createMock(SoapClient::class);
        $transaction->method('placeRequest')->willReturn(['ResultCode' => 'A', 'RefNum' => '123']);
        $provisioner = $this->createMock(CardProfileProvisioner::class);
        $provisioner->method('provision')->willThrowException(new \RuntimeException('Profile unavailable.'));
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('warning')->with('vault.profile_provision_failed', [
            'correlation_id' => 'test-correlation',
            'reason' => \RuntimeException::class,
        ]);

        $result = (new VaultingClient($transaction, $provisioner, $logger, $this->correlationId()))
            ->placeRequest($this->transfer(true));

        $this->assertSame('A', $result['ResultCode']);
        $this->assertSame('123', $result['RefNum']);
        $this->assertSame('failed', $result['VaultSaveStatus']);
        $this->assertArrayNotHasKey('PaymentMethodID', $result);
    }

    public function testApprovedTransactionWithoutVaultMetadataDoesNotProvision(): void
    {
        $transaction = $this->createMock(SoapClient::class);
        $transaction->method('placeRequest')->willReturn(['ResultCode' => 'A']);
        $provisioner = $this->createMock(CardProfileProvisioner::class);
        $provisioner->expects($this->never())->method('provision');

        $result = (new VaultingClient(
            $transaction,
            $provisioner,
            $this->createMock(Logger::class),
            $this->correlationId()
        ))
            ->placeRequest($this->transfer(false));

        $this->assertSame(['ResultCode' => 'A'], $result);
    }

    private function transfer(bool $withMetadata): TransferInterface
    {
        $body = ['securityToken' => [], 'tran' => ['CreditCardData' => []]];
        if ($withMetadata) {
            $body['__vault_profile'] = ['customer' => [], 'payment_method' => []];
        }
        $transfer = $this->createMock(TransferInterface::class);
        $transfer->method('getBody')->willReturn($body);
        $transfer->method('getHeaders')->willReturn(['store_id' => 1]);
        return $transfer;
    }

    private function correlationId(): CorrelationIdProvider
    {
        $provider = $this->createMock(CorrelationIdProvider::class);
        $provider->method('get')->willReturn('test-correlation');
        return $provider;
    }
}
