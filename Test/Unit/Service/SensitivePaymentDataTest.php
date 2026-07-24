<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Service;

use Gtstudio\Ebizcharge\Service\SensitivePaymentData;
use PHPUnit\Framework\TestCase;

class SensitivePaymentDataTest extends TestCase
{
    public function testStoresAndConsumesCardDataByQuoteId(): void
    {
        $data = new SensitivePaymentData();
        $data->storeCardData(123, '4000100011112224', '123');

        $this->assertSame(
            ['cc_number' => '4000100011112224', 'cc_cid' => '123'],
            $data->consumeCardData(123)
        );
    }

    public function testConsumptionClearsCardData(): void
    {
        $data = new SensitivePaymentData();
        $data->storeCardData(123, '4000100011112224', '123');
        $data->consumeCardData(123);

        $this->assertSame(
            ['cc_number' => '', 'cc_cid' => ''],
            $data->consumeCardData(123)
        );
    }

    public function testInvalidQuoteIdIsIgnored(): void
    {
        $data = new SensitivePaymentData();
        $data->storeCardData(0, '4000100011112224', '123');

        $this->assertSame(
            ['cc_number' => '', 'cc_cid' => ''],
            $data->consumeCardData(0)
        );
    }
}
