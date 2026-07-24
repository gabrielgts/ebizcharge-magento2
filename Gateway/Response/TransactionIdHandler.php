<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Response;

use Gtstudio\Ebizcharge\Service\CorrelationIdProvider;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class TransactionIdHandler implements HandlerInterface
{
    public function __construct(private readonly CorrelationIdProvider $correlationId)
    {
    }

    public function handle(array $handlingSubject, array $response): void
    {
        $payment = SubjectReader::readPayment($handlingSubject)->getPayment();

        $refNum = (string) ($response['RefNum'] ?? '');
        if ($refNum !== '') {
            $payment->setTransactionId($refNum);
            $payment->setCcTransId($refNum);
            $payment->setLastTransId($refNum);
        }

        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);
        $payment->setAdditionalInformation('gtstudio_correlation_id', $this->correlationId->get());
    }
}
