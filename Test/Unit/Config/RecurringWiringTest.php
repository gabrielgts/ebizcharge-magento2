<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Config;

use PHPUnit\Framework\TestCase;

class RecurringWiringTest extends TestCase
{
    public function testCheckoutValidationRunsBeforePaymentAndCreationRunsAfterVaultPersistence(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__, 3) . '/etc/events.xml');
        $this->assertInstanceOf(\SimpleXMLElement::class, $xml);

        $validators = $xml->xpath(
            '/config/event[@name="sales_model_service_quote_submit_before"]'
            . '/observer[@instance="Gtstudio\Ebizcharge\Observer\ValidateSubscriptionCheckout"]'
        );
        $creators = $xml->xpath(
            '/config/event[@name="checkout_submit_all_after"]'
            . '/observer[@instance="Gtstudio\Ebizcharge\Observer\CreateSubscriptionFromOrder"]'
        );
        $earlyCreators = $xml->xpath(
            '/config/event[@name="sales_order_place_after"]'
            . '/observer[@instance="Gtstudio\Ebizcharge\Observer\CreateSubscriptionFromOrder"]'
        );

        $this->assertNotEmpty($validators);
        $this->assertNotEmpty($creators);
        $this->assertEmpty($earlyCreators);
    }

    public function testRecurringCronsRetainExpectedCadence(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__, 3) . '/etc/crontab.xml');
        $this->assertInstanceOf(\SimpleXMLElement::class, $xml);

        $schedule = $xml->xpath(
            '/config/group/job[@name="gtstudio_ebizcharge_subscription_schedule"]/schedule'
        );
        $charge = $xml->xpath(
            '/config/group/job[@name="gtstudio_ebizcharge_subscription_charge"]/schedule'
        );

        $this->assertSame('0 * * * *', trim((string) $schedule[0]));
        $this->assertSame('*/15 * * * *', trim((string) $charge[0]));
    }
}
