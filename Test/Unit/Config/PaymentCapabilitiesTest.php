<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Config;

use PHPUnit\Framework\TestCase;

class PaymentCapabilitiesTest extends TestCase
{
    public function testCardMethodAdvertisesSaleCapabilityForAuthorizeCapture(): void
    {
        $config = $this->paymentConfig();

        $this->assertSame('1', (string) $config->gtstudio_ebizcharge->can_sale);
        $this->assertSame('1', (string) $config->gtstudio_ebizcharge->can_capture);
        $this->assertSame('1', (string) $config->gtstudio_ebizcharge->can_authorize_vault);
        $this->assertSame('1', (string) $config->gtstudio_ebizcharge->can_capture_vault);
    }

    public function testVaultFacadeAdvertisesSaleCapabilityForConfigurationParity(): void
    {
        $config = $this->paymentConfig();

        $this->assertSame('1', (string) $config->gtstudio_ebizcharge_cc_vault->can_sale);
        $this->assertSame('0', (string) $config->gtstudio_ebizcharge_cc_vault->can_use_internal);
    }

    private function paymentConfig(): \SimpleXMLElement
    {
        $config = simplexml_load_file(__DIR__ . '/../../../etc/config.xml');
        $this->assertInstanceOf(\SimpleXMLElement::class, $config);

        return $config->default->payment;
    }
}
