<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Service;

use Gtstudio\Ebizcharge\Gateway\Http\SoapMethodClient;
use Gtstudio\Ebizcharge\Service\CardProfileProvisioner;
use Gtstudio\Ebizcharge\Service\CustomerIdentity;
use Gtstudio\Ebizcharge\Service\CustomerIdentityManager;
use PHPUnit\Framework\TestCase;

class CardProfileProvisionerTest extends TestCase
{
    public function testResolvedIdentityCreatesOnlyPaymentMethodProfile(): void
    {
        $profileRequest = [];
        $soap = $this->createMock(SoapMethodClient::class);
        $soap->expects($this->once())->method('call')
            ->with('AddCustomerPaymentMethodProfile', $this->callback(
                static function (array $arguments) use (&$profileRequest): bool {
                    $profileRequest = $arguments;
                    return true;
                }
            ), [])
            ->willReturn(['AddCustomerPaymentMethodProfileResult' => '77']);
        $manager = $this->createMock(CustomerIdentityManager::class);
        $manager->expects($this->never())->method('sync');

        $result = (new CardProfileProvisioner($soap, $manager))->provision(
            $this->request(),
            $this->metadata(resolved: true)
        );

        $this->assertSame(['cust_num' => '00042', 'method_id' => '77'], $result);
        $this->assertSame('internal-1', $profileRequest['customerInternalId']);
        $this->assertSame([
            'MethodType' => 'CreditCard',
            'AccountHolderName' => 'Test Customer',
            'AvsZip' => '90001',
            'CardNumber' => '4111111111111111',
            'CardExpiration' => '1230',
            'CardCode' => '123',
        ], array_diff_key(
            $profileRequest['paymentMethodProfile'],
            array_flip(['Created', 'Modified'])
        ));
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/',
            $profileRequest['paymentMethodProfile']['Created']
        );
        $this->assertSame(
            $profileRequest['paymentMethodProfile']['Created'],
            $profileRequest['paymentMethodProfile']['Modified']
        );
    }

    public function testIncompleteMetadataUsesSharedCustomerIdentityManager(): void
    {
        $soap = $this->createMock(SoapMethodClient::class);
        $soap->expects($this->once())->method('call')
            ->with('AddCustomerPaymentMethodProfile', $this->callback(
                static fn (array $arguments): bool => $arguments['customerInternalId'] === 'internal-2'
            ), ['store_id' => 3])
            ->willReturn(['AddCustomerPaymentMethodProfileResult' => '78']);
        $manager = $this->createMock(CustomerIdentityManager::class);
        $manager->expects($this->once())->method('sync')
            ->with(123, $this->isType('array'), 3)
            ->willReturn(new CustomerIdentity(123, 'ERP-123', 'internal-2', '43', null, true, 'verified'));

        $result = (new CardProfileProvisioner($soap, $manager))->provision(
            $this->request(),
            $this->metadata(),
            ['store_id' => 3]
        );

        $this->assertSame(['cust_num' => '43', 'method_id' => '78'], $result);
    }

    public function testMalformedMethodResponseFailsSafely(): void
    {
        $soap = $this->createMock(SoapMethodClient::class);
        $soap->method('call')->willReturn(['Unexpected' => 'value']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('profile response is malformed');
        (new CardProfileProvisioner(
            $soap,
            $this->createMock(CustomerIdentityManager::class)
        ))->provision($this->request(), $this->metadata(resolved: true));
    }

    /** @return array<string,mixed> */
    private function request(): array
    {
        return [
            'securityToken' => ['SecurityId' => 'secret'],
            'tran' => ['CreditCardData' => [
                'CardNumber' => '4111111111111111',
                'CardExpiration' => '1230',
                'CardCode' => '123',
            ]],
        ];
    }

    /** @return array<string,mixed> */
    private function metadata(bool $resolved = false): array
    {
        return [
            'magento_customer_id' => 123,
            'customer' => ['CustomerId' => 'ERP-123', 'FirstName' => 'Test'],
            'payment_method' => [
                'MethodType' => 'CreditCard',
                'AccountHolderName' => 'Test Customer',
                'AvsZip' => '90001',
            ],
            'customer_internal_id' => $resolved ? 'internal-1' : '',
            'cust_num' => $resolved ? '00042' : '',
        ];
    }
}
