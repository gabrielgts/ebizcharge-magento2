<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Config;

use PHPUnit\Framework\TestCase;

class LineItemsWiringTest extends TestCase
{
    /** @dataProvider initialTransactionRequestProvider */
    public function testInitialCardAndVaultTransactionRequestsIncludeLineItemsBuilder(string $requestName): void
    {
        $this->assertSame(
            ['Gtstudio\Ebizcharge\Gateway\Request\LineItemsBuilder'],
            $this->builderValues($requestName, 'line_items')
        );
    }

    /** @return array<string,array{string}> */
    public static function initialTransactionRequestProvider(): array
    {
        return [
            'card authorize' => ['GtstudioEbizchargeAuthorizeRequest'],
            'card sale' => ['GtstudioEbizchargeSaleRequest'],
            'vault authorize' => ['GtstudioEbizchargeVaultAuthorizeRequest'],
            'vault sale' => ['GtstudioEbizchargeVaultSaleRequest'],
        ];
    }

    /** @dataProvider referenceOnlyRequestProvider */
    public function testReferenceOnlyRequestsDoNotIncludeLineItemsBuilder(string $requestName): void
    {
        $this->assertSame([], $this->builderValues($requestName, 'line_items'));
    }

    /** @return array<string,array{string}> */
    public static function referenceOnlyRequestProvider(): array
    {
        return [
            'capture' => ['GtstudioEbizchargeCaptureRequest'],
            'refund' => ['GtstudioEbizchargeRefundRequest'],
            'void' => ['GtstudioEbizchargeVoidRequest'],
        ];
    }

    /** @return string[] */
    private function builderValues(string $requestName, string $builderName): array
    {
        $di = simplexml_load_file(__DIR__ . '/../../../etc/di.xml');
        $this->assertInstanceOf(\SimpleXMLElement::class, $di);

        $nodes = $di->xpath(sprintf(
            '/config/virtualType[@name="%s"]/arguments/argument[@name="builders"]/item[@name="%s"]',
            $requestName,
            $builderName
        ));
        $this->assertIsArray($nodes);

        return array_map(static fn (\SimpleXMLElement $node): string => (string) $node, $nodes);
    }
}
