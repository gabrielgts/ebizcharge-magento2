<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Plugin\Vault;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Plugin\Vault\DeleteStoredCardPlugin;
use Gtstudio\Ebizcharge\Service\CardProfileDeleter;
use Gtstudio\Ebizcharge\Service\CorrelationIdProvider;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use PHPUnit\Framework\TestCase;

class DeleteStoredCardPluginTest extends TestCase
{
    public function testDeletesMatchingRemoteProfileBeforeLocalRepositoryDelete(): void
    {
        $deleter = $this->createMock(CardProfileDeleter::class);
        $deleter->expects($this->once())->method('delete')->with('00042:7', 2);
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->never())->method('warning');

        $result = (new DeleteStoredCardPlugin($deleter, $logger, $this->correlationId()))
            ->beforeDelete($this->createMock(PaymentTokenRepositoryInterface::class), $this->token());

        $this->assertNull($result);
    }

    public function testRemoteFailureStillAllowsLocalDeletionAndLogsNoSensitiveMessage(): void
    {
        $deleter = $this->createMock(CardProfileDeleter::class);
        $deleter->method('delete')->willThrowException(
            new \RuntimeException('PAN 4111111111111111 must never be copied into this warning')
        );
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('warning')->with('vault.remote_delete_failed', [
            'correlation_id' => 'delete-correlation',
            'reason' => \RuntimeException::class,
        ]);

        $result = (new DeleteStoredCardPlugin($deleter, $logger, $this->correlationId()))
            ->beforeDelete($this->createMock(PaymentTokenRepositoryInterface::class), $this->token());

        $this->assertNull($result);
    }

    public function testIgnoresUnrelatedPaymentProvider(): void
    {
        $deleter = $this->createMock(CardProfileDeleter::class);
        $deleter->expects($this->never())->method('delete');
        $token = $this->token('another_gateway');

        $result = (new DeleteStoredCardPlugin(
            $deleter,
            $this->createMock(Logger::class),
            $this->correlationId()
        ))->beforeDelete($this->createMock(PaymentTokenRepositoryInterface::class), $token);

        $this->assertNull($result);
    }

    private function token(string $paymentMethodCode = Config::METHOD_CODE): PaymentTokenInterface
    {
        $token = $this->createMock(PaymentTokenInterface::class);
        $token->method('getPaymentMethodCode')->willReturn($paymentMethodCode);
        $token->method('getGatewayToken')->willReturn('00042:7');
        $token->method('getWebsiteId')->willReturn(2);
        return $token;
    }

    private function correlationId(): CorrelationIdProvider
    {
        $provider = $this->createMock(CorrelationIdProvider::class);
        $provider->method('get')->willReturn('delete-correlation');
        return $provider;
    }
}
