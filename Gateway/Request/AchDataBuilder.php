<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Reads ACH account/routing/type from the payment additional_information bag set by the checkout JS,
 * builds the CheckData block, and IMMEDIATELY clears those fields off additional_information so they
 * do not survive into Magento persistence.
 *
 * Routing's last 3 digits are masked with 'XXX' on the payment record (parity with the legacy module's
 * PciCompliancePayment observer). Account is reduced to last 4.
 *
 * This is symmetric to PaymentDataBuilder for credit cards. PAN/CVV-equivalent ACH data also lives
 * only in the request array we return; the SOAP client serializes once and the array is dereferenced.
 */
class AchDataBuilder implements BuilderInterface
{
    public const KEY_ACCOUNT = 'ach_account';
    public const KEY_ROUTING = 'ach_routing';
    public const KEY_ACCOUNT_TYPE = 'ach_type';

    public const TYPE_CHECKING = 'checking';
    public const TYPE_SAVINGS = 'savings';

    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $info = $payment->getAdditionalInformation();
        $account = trim((string) ($info[self::KEY_ACCOUNT] ?? ''));
        $routing = trim((string) ($info[self::KEY_ROUTING] ?? ''));
        $type = strtolower(trim((string) ($info[self::KEY_ACCOUNT_TYPE] ?? self::TYPE_CHECKING)));

        $payment->unsAdditionalInformation(self::KEY_ACCOUNT);
        $payment->unsAdditionalInformation(self::KEY_ROUTING);

        if ($account !== '') {
            $payment->setAdditionalInformation('ach_last4', substr($account, -4));
        }
        if ($routing !== '') {
            $payment->setAdditionalInformation('ach_routing_masked', $this->maskRouting($routing));
        }
        if ($type !== '') {
            $payment->setAdditionalInformation(self::KEY_ACCOUNT_TYPE, $type);
        }

        if ($type !== self::TYPE_CHECKING && $type !== self::TYPE_SAVINGS) {
            $type = self::TYPE_CHECKING;
        }

        return [
            'tran' => array_filter([
                'CheckData' => array_filter([
                    'Account' => $account,
                    'Routing' => $routing,
                    'AccountType' => $type,
                ], static fn ($v) => $v !== ''),
            ], static fn ($v) => $v !== []),
        ];
    }

    private function maskRouting(string $routing): string
    {
        if (strlen($routing) <= 3) {
            return str_repeat('X', strlen($routing));
        }
        return substr($routing, 0, -3) . 'XXX';
    }
}
