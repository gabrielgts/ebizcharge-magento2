<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Request;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class AmountBuilder implements BuilderInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    public function build(array $buildSubject): array
    {
        $amount = SubjectReader::readAmount($buildSubject);
        $payment = SubjectReader::readPayment($buildSubject);
        $order = $payment->getOrder();
        $storeId = $order?->getStoreId();
        $storeId = $storeId === null ? null : (int) $storeId;

        $description = str_replace(
            '[orderid]',
            (string) ($order?->getOrderIncrementId() ?? ''),
            $this->config->getDescription($storeId)
        );

        $salesOrder = $payment->getPayment()->getOrder();

        return [
            'tran' => [
                'Details' => [
                    'Amount' => round((float) $amount, 2),
                    'Description' => $description,
                    'Currency' => $order?->getCurrencyCode() ?? '',
                    'Invoice' => $this->truncateInvoice((string) ($order?->getOrderIncrementId() ?? '')),
                    'OrderID' => substr((string) ($order?->getOrderIncrementId() ?? ''), 0, 64),
                    // TransactionDetail declares the fields below as minOccurs="1" non-nillable;
                    // PHP's SOAP encoder rejects the request outright if any is absent. Tax and
                    // Shipping carry real order values (as the legacy module sent them from
                    // Model/Payment.php); the rest are the legacy defaults. Amount above is the
                    // authoritative charged value — these are Level II reporting fields only.
                    'Tax' => round((float) $salesOrder->getTaxAmount(), 2),
                    'Shipping' => round((float) $salesOrder->getShippingAmount(), 2),
                    'Subtotal' => 0.0,
                    'Discount' => 0.0,
                    'Duty' => 0.0,
                    'Tip' => 0.0,
                    'NonTax' => false,
                    'AllowPartialAuth' => false,
                ],
                'Software' => $this->config->getSoftwareTag($storeId),
            ],
        ];
    }

    /**
     * EBizCharge "Invoice" field is constrained to 10 numeric digits historically; keep last 10 numeric chars.
     */
    private function truncateInvoice(string $orderIncrementId): string
    {
        $digits = preg_replace('/\D/', '', $orderIncrementId) ?? '';
        return substr($digits, -10);
    }
}
