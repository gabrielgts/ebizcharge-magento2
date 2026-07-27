<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Request;

use Gtstudio\Ebizcharge\Service\SensitivePaymentData;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

/** Builds request-local credit-card data. */
class PaymentDataBuilder implements BuilderInterface
{
    public const KEY_CC_NUMBER = 'cc_number';
    public const KEY_CC_CID = 'cc_cid';
    public const KEY_CC_EXP_MONTH = 'cc_exp_month';
    public const KEY_CC_EXP_YEAR = 'cc_exp_year';

    public function __construct(private readonly SensitivePaymentData $sensitivePaymentData)
    {
    }

    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $info = $payment->getAdditionalInformation();
        $order = $payment->getOrder();
        $sensitive = $this->sensitivePaymentData->consumeCardData((int) ($order?->getQuoteId() ?? 0));
        $pan = $sensitive[self::KEY_CC_NUMBER];
        $cvv = $sensitive[self::KEY_CC_CID];
        $month = str_pad((string) ($info[self::KEY_CC_EXP_MONTH] ?? ''), 2, '0', STR_PAD_LEFT);
        $year = (string) ($info[self::KEY_CC_EXP_YEAR] ?? '');
        $expYY = strlen($year) === 4 ? substr($year, -2) : $year;

        $payment->unsAdditionalInformation(self::KEY_CC_NUMBER);
        $payment->unsAdditionalInformation(self::KEY_CC_CID);

        if ($pan !== '') {
            $payment->setCcLast4(substr($pan, -4));
        }
        if ($month !== '') {
            $payment->setCcExpMonth($month);
        }
        if ($year !== '') {
            $payment->setCcExpYear($year);
        }

        $cardData = array_filter([
            'CardNumber' => $pan,
            'CardExpiration' => $month !== '' && $expYY !== '' ? ($month . $expYY) : '',
            'CardCode' => $cvv,
        ], static fn ($v) => $v !== '');

        if ($cardData === []) {
            return [];
        }

        // SOAP requires both flags for server-side card-not-present requests.
        $cardData['InternalCardAuth'] = false;
        $cardData['CardPresent'] = false;

        return ['tran' => ['CreditCardData' => $cardData]];
    }
}
