<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Gateway\Request;

use Gtstudio\Ebizcharge\Gateway\Request\LineItemsBuilder;
use Magento\Framework\DataObject;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use PHPUnit\Framework\TestCase;

class LineItemsBuilderTest extends TestCase
{
    public function testBuildsLineItemsFromVisibleMagentoOrderItems(): void
    {
        $builder = new LineItemsBuilder();

        $result = $builder->build([
            'payment' => $this->paymentDataObject($this->paymentInfo([
                $this->orderItem([
                    'sku' => 'BL-ONE',
                    'name' => 'Brilliant Sewing Machine',
                    'description' => 'A real Baby Lock product',
                    'base_price' => 164.0,
                    'qty_ordered' => 2.0,
                    'base_tax_amount' => 12.29,
                    'product_id' => 101,
                    'base_discount_amount' => 0.80,
                    'discount_percent' => 5.0,
                    'tax_percent' => 7.5,
                    'product_type' => 'simple',
                ]),
                $this->orderItem([
                    'sku' => 'BL-TWO',
                    'name' => 'Accessory Pack',
                    'base_price' => 0.80,
                    'qty_ordered' => 1.0,
                    'tax_amount' => 0.06,
                    'product_id' => 102,
                    'discount_amount' => 0.0,
                    'product_type' => 'simple',
                ]),
            ])),
        ]);

        $this->assertSame([
            [
                'DiscountRate' => 5.0,
                'ProductRefNum' => '101',
                'SKU' => 'BL-ONE',
                'CommodityCode' => '',
                'ProductName' => 'Brilliant Sewing Machine',
                'Description' => 'A real Baby Lock product',
                'DiscountAmount' => 0.80,
                'TaxRate' => 7.5,
                'UnitOfMeasure' => 'EA',
                'UnitPrice' => 164.0,
                'Qty' => 2.0,
                'Taxable' => true,
                'TaxAmount' => 12.29,
            ],
            [
                'DiscountRate' => 0.0,
                'ProductRefNum' => '102',
                'SKU' => 'BL-TWO',
                'CommodityCode' => '',
                'ProductName' => 'Accessory Pack',
                'Description' => 'Accessory Pack',
                'DiscountAmount' => 0.0,
                'TaxRate' => 0.0,
                'UnitOfMeasure' => 'EA',
                'UnitPrice' => 0.80,
                'Qty' => 1.0,
                'Taxable' => true,
                'TaxAmount' => 0.06,
            ],
        ], $result['tran']['LineItems']);
    }

    public function testUsesVisibleParentStyleSkuAndName(): void
    {
        $builder = new LineItemsBuilder();

        $result = $builder->build([
            'payment' => $this->paymentDataObject($this->paymentInfo([
                $this->orderItem([
                    'sku' => 'CONFIG-PARENT',
                    'name' => 'Configurable Product Choice',
                    'base_price' => 25.0,
                    'qty_ordered' => 1.0,
                    'product_type' => 'configurable',
                ]),
            ])),
        ]);

        $this->assertSame('CONFIG-PARENT', $result['tran']['LineItems'][0]['SKU']);
        $this->assertSame('Configurable Product Choice', $result['tran']['LineItems'][0]['ProductName']);
    }

    public function testSkipsZeroPriceNonVirtualItemsButKeepsZeroPriceVirtualItems(): void
    {
        $builder = new LineItemsBuilder();

        $result = $builder->build([
            'payment' => $this->paymentDataObject($this->paymentInfo([
                $this->orderItem([
                    'sku' => 'FREE-SIMPLE',
                    'name' => 'Free Simple Item',
                    'base_price' => 0.0,
                    'qty_ordered' => 1.0,
                    'product_type' => 'simple',
                ]),
                $this->orderItem([
                    'sku' => 'FREE-VIRTUAL',
                    'name' => 'Free Virtual Item',
                    'base_price' => 0.0,
                    'qty_ordered' => 1.0,
                    'product_type' => 'virtual',
                ]),
            ])),
        ]);

        $this->assertCount(1, $result['tran']['LineItems']);
        $this->assertSame('FREE-VIRTUAL', $result['tran']['LineItems'][0]['SKU']);
        $this->assertSame(0.0, $result['tran']['LineItems'][0]['UnitPrice']);
    }

    public function testReturnsEmptyPayloadWhenNoEligibleItemsExist(): void
    {
        $builder = new LineItemsBuilder();

        $this->assertSame([], $builder->build([
            'payment' => $this->paymentDataObject($this->paymentInfo([
                $this->orderItem([
                    'sku' => 'FREE-SIMPLE',
                    'name' => 'Free Simple Item',
                    'base_price' => 0.0,
                    'qty_ordered' => 1.0,
                    'product_type' => 'simple',
                ]),
            ])),
        ]));
    }

    /** @param array<int,object> $items */
    private function paymentInfo(array $items): InfoInterface
    {
        return new class ($items) extends DataObject implements InfoInterface {
            /** @param array<int,object> $items */
            public function __construct(array $items)
            {
                parent::__construct(['order' => new DataObject(['all_visible_items' => $items])]);
            }

            public function encrypt($data)
            {
                return $data;
            }

            public function decrypt($data)
            {
                return $data;
            }

            public function setAdditionalInformation($key, $value = null)
            {
                return $this;
            }

            public function hasAdditionalInformation($key = null)
            {
                return false;
            }

            public function unsAdditionalInformation($key = null)
            {
                return $this;
            }

            public function getAdditionalInformation($key = null)
            {
                return $key === null ? [] : null;
            }

            public function getMethodInstance()
            {
                return null;
            }
        };
    }

    /** @param array<string,mixed> $data */
    private function orderItem(array $data): object
    {
        return new DataObject($data);
    }

    private function paymentDataObject(InfoInterface $payment): PaymentDataObjectInterface
    {
        return new class ($payment) implements PaymentDataObjectInterface {
            public function __construct(private readonly InfoInterface $payment)
            {
            }

            public function getOrder()
            {
                return null;
            }

            public function getPayment()
            {
                return $this->payment;
            }
        };
    }
}
