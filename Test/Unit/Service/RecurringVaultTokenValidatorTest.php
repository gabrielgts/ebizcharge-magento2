<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Service;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Service\RecurringVaultTokenValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use PHPUnit\Framework\TestCase;

class RecurringVaultTokenValidatorTest extends TestCase
{
    public function testAcceptsOwnedActiveCardForSubscriptionWebsite(): void
    {
        $token = $this->token();

        $this->assertSame($token, $this->validator($token)->validate($this->subscription()));
    }

    public function testRejectsUnrelatedProvider(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('not available');

        $this->validator($this->token('another_gateway'))->validate($this->subscription());
    }

    public function testRejectsExpiredCard(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('expired');

        $this->validator($this->token(Config::METHOD_CODE, '2020-01-01 00:00:00'))
            ->validate($this->subscription());
    }

    private function validator(PaymentTokenInterface $token): RecurringVaultTokenValidator
    {
        $repository = $this->createMock(PaymentTokenRepositoryInterface::class);
        $repository->method('getById')->with(9)->willReturn($token);
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(2);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with(1)->willReturn($store);
        return new RecurringVaultTokenValidator($repository, $storeManager);
    }

    private function token(
        string $methodCode = Config::METHOD_CODE,
        string $expiresAt = '2035-12-31 23:59:59'
    ): PaymentTokenInterface {
        $token = $this->createMock(PaymentTokenInterface::class);
        $token->method('getEntityId')->willReturn(9);
        $token->method('getCustomerId')->willReturn(123);
        $token->method('getIsActive')->willReturn(true);
        $token->method('getIsVisible')->willReturn(true);
        $token->method('getPaymentMethodCode')->willReturn($methodCode);
        $token->method('getPublicHash')->willReturn('public-hash');
        $token->method('getGatewayToken')->willReturn('42:7');
        $token->method('getWebsiteId')->willReturn(2);
        $token->method('getExpiresAt')->willReturn($expiresAt);
        return $token;
    }

    private function subscription(): SubscriptionInterface
    {
        $subscription = $this->createMock(SubscriptionInterface::class);
        $subscription->method('getVaultPaymentTokenId')->willReturn(9);
        $subscription->method('getCustomerId')->willReturn(123);
        $subscription->method('getStoreId')->willReturn(1);
        return $subscription;
    }
}
