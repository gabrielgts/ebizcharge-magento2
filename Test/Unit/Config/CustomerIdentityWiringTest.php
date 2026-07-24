<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Config;

use PHPUnit\Framework\TestCase;

class CustomerIdentityWiringTest extends TestCase
{
    public function testIdentityUsesModuleOwnedTableWithoutExtendingCustomerEntity(): void
    {
        $schema = (string) file_get_contents(__DIR__ . '/../../../etc/db_schema.xml');

        $this->assertStringContainsString(
            '<table name="gtstudio_ebizcharge_customer_identity"',
            $schema
        );
        $this->assertStringNotContainsString('<table name="customer_entity"', $schema);
        $this->assertStringContainsString('referenceTable="customer_entity"', $schema);
    }

    public function testCommandsAndAdminProviderAreRegisteredOnce(): void
    {
        $di = (string) file_get_contents(__DIR__ . '/../../../etc/di.xml');
        $adminDi = (string) file_get_contents(__DIR__ . '/../../../etc/adminhtml/di.xml');

        $this->assertSame(1, substr_count($di, 'CheckCustomerIdentity'));
        $this->assertSame(1, substr_count($di, 'SyncCustomerIdentity'));
        $this->assertSame(1, substr_count($adminDi, 'CustomerIdentityDataProviderPlugin'));
    }

    public function testReferenceOperationsDoNotGainCustomerBuilder(): void
    {
        $di = (string) file_get_contents(__DIR__ . '/../../../etc/di.xml');
        foreach (['Capture', 'Refund', 'Void'] as $operation) {
            $pattern = sprintf(
                '/<virtualType name="GtstudioEbizcharge%sRequest".*?<\\/virtualType>/s',
                $operation
            );
            $this->assertSame(1, preg_match($pattern, $di));
            preg_match($pattern, $di, $match);
            $this->assertStringNotContainsString('CustomerDataBuilder', $match[0]);
        }
    }
}
