<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class LineItemsBuilder implements BuilderInterface
{
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $payment->getOrder();

        if (!is_object($order) || !is_callable([$order, 'getAllVisibleItems'])) {
            return [];
        }

        $lineItems = [];
        foreach ((array) $order->getAllVisibleItems() as $item) {
            if (!is_object($item)) {
                continue;
            }

            $unitPrice = $this->firstFloat($item, ['getBasePrice', 'getPrice', 'getFinalPrice']);
            $productType = strtolower($this->firstString($item, ['getProductType']));
            if ($unitPrice <= 0.0 && $productType !== 'virtual') {
                continue;
            }

            $name = $this->firstString($item, ['getName']);
            $description = $this->firstString($item, ['getDescription']);
            if ($description === '') {
                $description = $name;
            }

            $taxAmount = $this->firstFloat($item, ['getBaseTaxAmount', 'getTaxAmount']);
            $line = [
                'DiscountRate' => round($this->firstFloat($item, ['getDiscountPercent']), 4),
                'ProductRefNum' => $this->firstString($item, ['getProductId']),
                'SKU' => $this->firstString($item, ['getSku']),
                'CommodityCode' => '',
                'ProductName' => $name,
                'Description' => $description,
                'DiscountAmount' => round($this->firstFloat($item, ['getBaseDiscountAmount', 'getDiscountAmount']), 2),
                'TaxRate' => round($this->firstFloat($item, ['getTaxPercent']), 4),
                'UnitOfMeasure' => 'EA',
                'UnitPrice' => round($unitPrice, 2),
                'Qty' => $this->firstFloat($item, ['getQtyOrdered', 'getQty']),
                'Taxable' => $taxAmount > 0.0,
                'TaxAmount' => round($taxAmount, 2),
            ];

            $lineItems[] = $line;
        }

        if ($lineItems === []) {
            return [];
        }

        return ['tran' => ['LineItems' => $lineItems]];
    }

    /** @param string[] $getters */
    private function firstString(object $source, array $getters): string
    {
        foreach ($getters as $getter) {
            $value = $this->callGetter($source, $getter);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    /** @param string[] $getters */
    private function firstFloat(object $source, array $getters): float
    {
        foreach ($getters as $getter) {
            $value = $this->callGetter($source, $getter);
            if ($value !== null && $value !== '') {
                return (float) $value;
            }
        }

        return 0.0;
    }

    private function callGetter(object $source, string $getter): mixed
    {
        if (!is_callable([$source, $getter])) {
            return null;
        }

        try {
            return $source->{$getter}();
        } catch (\Throwable) {
            return null;
        }
    }
}
