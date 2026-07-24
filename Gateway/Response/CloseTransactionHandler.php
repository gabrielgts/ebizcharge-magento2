<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

/**
 * Closes the payment transaction (used by void/refund commands).
 */
class CloseTransactionHandler implements HandlerInterface
{
    public function __construct(private readonly bool $shouldCloseParent = true)
    {
    }

    public function handle(array $handlingSubject, array $response): void
    {
        $payment = SubjectReader::readPayment($handlingSubject)->getPayment();
        $payment->setIsTransactionClosed(true);
        $payment->setShouldCloseParentTransaction($this->shouldCloseParent);
    }
}
