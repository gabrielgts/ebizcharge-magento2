<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

/** Builds ACH data and removes raw values before persistence. */
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

        if ($account === '' && $routing === '') {
            return ['tran' => []];
        }

        if ($type !== self::TYPE_CHECKING && $type !== self::TYPE_SAVINGS) {
            $type = self::TYPE_CHECKING;
        }

        if ($account !== '') {
            $payment->setAdditionalInformation('ach_last4', substr($account, -4));
        }
        if ($routing !== '') {
            $payment->setAdditionalInformation('ach_routing_masked', $this->maskRouting($routing));
        }
        if ($type !== '') {
            $payment->setAdditionalInformation(self::KEY_ACCOUNT_TYPE, $type);
        }

        return [
            'tran' => array_filter([
                'CheckData' => array_filter([
                    'Account' => $account,
                    'Routing' => $routing,
                    'AccountType' => $type,
                ], static fn ($value) => $value !== ''),
            ], static fn ($value) => $value !== []),
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
