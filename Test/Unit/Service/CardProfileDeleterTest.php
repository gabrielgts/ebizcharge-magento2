<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Service;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Gateway\Http\SoapMethodClient;
use Gtstudio\Ebizcharge\Service\CardProfileDeleter;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class CardProfileDeleterTest extends TestCase
{
    public function testUsesExactDeleteProfileContract(): void
    {
        $soap = $this->createMock(SoapMethodClient::class);
        $soap->expects($this->once())->method('call')->with(
            'DeleteCustomerPaymentMethodProfile',
            [
                'securityToken' => [
                    'UserId' => 'user',
                    'SecurityId' => 'key',
                    'Password' => 'password',
                ],
                'customerToken' => '00042',
                'paymentMethodId' => '007',
            ],
            ['store_id' => null]
        )->willReturn(['DeleteCustomerPaymentMethodProfileResult' => true]);

        $this->deleter($soap)->delete('00042:007');
        $this->addToAssertionCount(1);
    }

    public function testRejectsMalformedTokenBeforeSoap(): void
    {
        $soap = $this->createMock(SoapMethodClient::class);
        $soap->expects($this->never())->method('call');
        $this->expectException(\InvalidArgumentException::class);

        $this->deleter($soap)->delete('malformed');
    }

    public function testUnconfirmedRemoteDeleteFailsForPluginToHandleBestEffort(): void
    {
        $soap = $this->createMock(SoapMethodClient::class);
        $soap->method('call')->willReturn(['DeleteCustomerPaymentMethodProfileResult' => false]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('did not confirm');

        $this->deleter($soap)->delete('42:7');
    }

    private function deleter(SoapMethodClient $soap): CardProfileDeleter
    {
        $config = $this->createMock(Config::class);
        $config->method('getUserId')->willReturn('user');
        $config->method('getSecurityId')->willReturn('key');
        $config->method('getPassword')->willReturn('password');
        return new CardProfileDeleter(
            $soap,
            $config,
            $this->createMock(StoreManagerInterface::class)
        );
    }
}
