<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Service;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Gateway\Http\SoapFaultException;
use Gtstudio\Ebizcharge\Gateway\Http\SoapMethodClient;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Service\CorrelationIdProvider;
use Gtstudio\Ebizcharge\Service\CustomerIdentity;
use Gtstudio\Ebizcharge\Service\CustomerIdentityManager;
use Gtstudio\Ebizcharge\Service\CustomerIdentityStorage;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SoapFault;

class CustomerIdentityManagerTest extends TestCase
{
    public function testCompleteCachedIdentityDoesNotCallSoap(): void
    {
        $storage = $this->storage(new CustomerIdentity(
            12,
            'ERP-12',
            'internal-12',
            '00042',
            null,
            true,
            'cached'
        ));
        $soap = $this->createMock(SoapMethodClient::class);
        $soap->expects($this->never())->method('call');

        $identity = $this->manager($storage, $soap)->resolveForTransaction(12, [], 3);

        $this->assertSame('ERP-12', $identity->customerId);
        $this->assertSame('00042', $identity->customerNumber);
    }

    public function testExistingRemoteCustomerIsResolvedAndPersisted(): void
    {
        $storage = $this->storage(new CustomerIdentity(12, 'ERP-12', '', '', null, true));
        $storage->expects($this->once())->method('saveResolved')->with($this->callback(
            static fn (CustomerIdentity $identity): bool => $identity->customerInternalId === 'internal-12'
                && $identity->customerNumber === '00042'
        ));
        $soap = $this->createMock(SoapMethodClient::class);
        $soap->method('call')->willReturnCallback(static fn (string $method): array => match ($method) {
            'GetCustomer' => ['CustomerId' => 'ERP-12', 'CustomerInternalId' => 'internal-12'],
            'GetCustomerToken' => ['GetCustomerTokenResult' => '00042'],
            default => throw new \LogicException('Unexpected SOAP method'),
        });

        $identity = $this->manager($storage, $soap)->sync(12, ['FirstName' => 'Ada'], 3);

        $this->assertSame('verified', $identity->status);
        $this->assertSame('00042', $identity->customerNumber);
    }

    public function testNotFoundCustomerIsCreatedWithDeterministicId(): void
    {
        $storage = $this->storage(new CustomerIdentity(12, '12'));
        $soap = $this->createMock(SoapMethodClient::class);
        $addRequest = [];
        $soap->method('call')->willReturnCallback(function (
            string $method,
            array $arguments
        ) use (&$addRequest): array {
            if ($method === 'GetCustomer') {
                throw new SoapFaultException(
                    's:NotFound',
                    'Customer Not Found',
                    new SoapFault('s:NotFound', 'Customer Not Found')
                );
            }
            if ($method === 'AddCustomer') {
                $addRequest = $arguments;
                return ['Status' => 'Success', 'CustomerId' => '12', 'CustomerInternalId' => 'internal-12'];
            }
            if ($method === 'GetCustomerToken') {
                return ['GetCustomerTokenResult' => '0042'];
            }
            throw new \LogicException('Unexpected SOAP method');
        });

        $identity = $this->manager($storage, $soap)->sync(12, ['FirstName' => 'Ada'], 3);

        $this->assertSame('12', $addRequest['customer']['CustomerId']);
        $this->assertSame('Ada', $addRequest['customer']['FirstName']);
        $this->assertSame('Magento2-Gtstudio', $addRequest['customer']['SoftwareId']);
        $this->assertSame('0042', $identity->customerNumber);
    }

    public function testDuplicateCreationResponseRetriesLookup(): void
    {
        $storage = $this->storage(new CustomerIdentity(12, '12'));
        $soap = $this->createMock(SoapMethodClient::class);
        $lookupCount = 0;
        $soap->method('call')->willReturnCallback(function (string $method) use (&$lookupCount): array {
            if ($method === 'GetCustomer') {
                ++$lookupCount;
                if ($lookupCount === 1) {
                    throw new SoapFaultException(
                        's:NotFound',
                        'Not Found',
                        new SoapFault('s:NotFound', 'Not Found')
                    );
                }
                return ['CustomerId' => '12', 'CustomerInternalId' => 'internal-race'];
            }
            return match ($method) {
                'AddCustomer' => ['Status' => 'Failed', 'ErrorCode' => '2', 'Error' => 'Record already exists'],
                'GetCustomerToken' => ['GetCustomerTokenResult' => '55'],
                default => throw new \LogicException('Unexpected SOAP method'),
            };
        });

        $identity = $this->manager($storage, $soap)->sync(12, [], 3);

        $this->assertSame(2, $lookupCount);
        $this->assertSame('internal-race', $identity->customerInternalId);
    }

    public function testCheckoutContinuesWithCustomerIdWhenProvisioningFails(): void
    {
        $storage = $this->storage(new CustomerIdentity(12, 'ERP-12', '', '', null, true));
        $soap = $this->createMock(SoapMethodClient::class);
        $soap->method('call')->willThrowException(new \RuntimeException('network unavailable'));

        $identity = $this->manager($storage, $soap)->resolveForTransaction(12, [], 3);

        $this->assertSame('ERP-12', $identity->customerId);
        $this->assertSame('unverified', $identity->status);
    }

    public function testCheckIsReadOnlyAndDoesNotCreateOrPersist(): void
    {
        $storage = $this->storage(new CustomerIdentity(12, 'ERP-12'));
        $storage->expects($this->never())->method('saveResolved');
        $soap = $this->createMock(SoapMethodClient::class);
        $soap->method('call')->willReturnCallback(static fn (string $method): array => match ($method) {
            'GetCustomer' => ['CustomerId' => 'ERP-12', 'CustomerInternalId' => 'internal-12'],
            'GetCustomerToken' => ['GetCustomerTokenResult' => '42'],
            default => throw new \LogicException('Unexpected SOAP method'),
        });

        $identity = $this->manager($storage, $soap)->check(12, 3);

        $this->assertSame('verified', $identity->status);
    }

    public function testDuplicateLocalCustomerIdStopsSyncBeforeSoap(): void
    {
        $storage = $this->storage(new CustomerIdentity(12, 'ERP-DUPLICATE', '', '', null, true));
        $storage->expects($this->once())->method('assertCustomerIdAvailable')
            ->willThrowException(new LocalizedException(__('Duplicate mapping')));
        $soap = $this->createMock(SoapMethodClient::class);
        $soap->expects($this->never())->method('call');

        $this->expectException(LocalizedException::class);
        $this->manager($storage, $soap)->sync(12, [], 3);
    }

    public function testMismatchedRemoteCustomerFailsSafely(): void
    {
        $storage = $this->storage(new CustomerIdentity(12, 'ERP-12'));
        $storage->expects($this->never())->method('saveResolved');
        $soap = $this->createMock(SoapMethodClient::class);
        $soap->method('call')->willReturn([
            'CustomerId' => 'ERP-OTHER',
            'CustomerInternalId' => 'internal-other',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not match');
        $this->manager($storage, $soap)->sync(12, [], 3);
    }

    /** @return CustomerIdentityStorage&MockObject */
    private function storage(CustomerIdentity $identity): CustomerIdentityStorage
    {
        $storage = $this->createMock(CustomerIdentityStorage::class);
        $storage->method('get')->willReturn($identity);
        return $storage;
    }

    private function manager(CustomerIdentityStorage $storage, SoapMethodClient $soap): CustomerIdentityManager
    {
        $config = $this->createMock(Config::class);
        $config->method('getUserId')->willReturn('user');
        $config->method('getSecurityId')->willReturn('security');
        $config->method('getPassword')->willReturn('password');
        $config->method('getSoftwareTag')->willReturn('Magento2-Gtstudio');
        $correlation = $this->createMock(CorrelationIdProvider::class);
        $correlation->method('get')->willReturn('cid');

        return new CustomerIdentityManager(
            $storage,
            $soap,
            $config,
            $this->createMock(Logger::class),
            $correlation
        );
    }
}
