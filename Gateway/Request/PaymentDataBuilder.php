<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Request;

use Gtstudio\Ebizcharge\Service\SensitivePaymentData;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Consumes PAN/CVV from the request-local SensitivePaymentData handoff and reads non-sensitive
 * expiration fields from payment additional_information, then builds the CreditCardData block.
 *
 * PAN/CVV live only in memory and in the request array returned here; the SOAP client serializes
 * once, makes the call, and the array is dereferenced.
 */
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

        // CreditCardData declares InternalCardAuth/CardPresent as minOccurs="1" non-nillable
        // booleans; PHP's SOAP encoder rejects the request if either is missing. Both are false
        // here — this is a server-side card-not-present capture, same as the legacy module sent.
        $cardData['InternalCardAuth'] = false;
        $cardData['CardPresent'] = false;

        return ['tran' => ['CreditCardData' => $cardData]];
    }
}
