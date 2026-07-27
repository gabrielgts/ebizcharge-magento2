<?php

declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Config;

use PHPUnit\Framework\TestCase;

class AdminGridWiringTest extends TestCase
{
    private const SEARCH_RESULT = 'Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult';

    public function testAdminDataSourcesUseSearchResultCollections(): void
    {
        $di = simplexml_load_file(dirname(__DIR__, 3) . '/etc/di.xml');
        self::assertNotFalse($di);

        $collectionFactory = $di->xpath(
            '/config/type[@name="Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory"]'
            . '/arguments/argument[@name="collections"]'
        );
        self::assertCount(1, $collectionFactory);

        $mappings = [];
        foreach ($collectionFactory[0]->item as $item) {
            $mappings[(string) $item['name']] = trim((string) $item);
        }

        self::assertSame(
            'Gtstudio\Ebizcharge\Model\ResourceModel\Subscription\Grid\Collection',
            $mappings['gtstudio_ebizcharge_subscription_listing_data_source'] ?? null
        );
        self::assertSame(
            'Gtstudio\Ebizcharge\Model\ResourceModel\DebugTrace\Grid\Collection',
            $mappings['gtstudio_ebizcharge_debug_trace_listing_data_source'] ?? null
        );

        $this->assertSearchResultVirtualType(
            $di,
            'Gtstudio\Ebizcharge\Model\ResourceModel\Subscription\Grid\Collection',
            'gtstudio_ebizcharge_subscription',
            'Gtstudio\Ebizcharge\Model\ResourceModel\Subscription'
        );
        $this->assertSearchResultVirtualType(
            $di,
            'Gtstudio\Ebizcharge\Model\ResourceModel\DebugTrace\Grid\Collection',
            'gtstudio_ebizcharge_debug_trace',
            'Gtstudio\Ebizcharge\Model\ResourceModel\DebugTrace'
        );
    }

    private function assertSearchResultVirtualType(
        \SimpleXMLElement $di,
        string $name,
        string $mainTable,
        string $resourceModel
    ): void {
        $matches = $di->xpath(sprintf('/config/virtualType[@name="%s"]', $name));
        self::assertCount(1, $matches);
        self::assertSame(self::SEARCH_RESULT, (string) $matches[0]['type']);

        $arguments = [];
        foreach ($matches[0]->arguments->argument as $argument) {
            $arguments[(string) $argument['name']] = trim((string) $argument);
        }
        self::assertSame($mainTable, $arguments['mainTable'] ?? null);
        self::assertSame($resourceModel, $arguments['resourceModel'] ?? null);
    }
}
