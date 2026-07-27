<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Request;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

/** Builds reference-based capture, refund, and void requests. */
class RefundCaptureVoidBuilder implements BuilderInterface
{
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $refNum = (string) (
            $payment->getCcTransId()
            ?: $payment->getLastTransId()
            ?: $payment->getParentTransactionId()
            ?: ''
        );

        if ($refNum === '') {
            throw new LocalizedException(__('Unable to find original transaction.'));
        }

        return [
            'tran' => [
                'RefNum' => (int) $refNum,
            ],
        ];
    }
}
