<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Request;

use Gtstudio\Ebizcharge\Service\CorrelationIdProvider;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

/** Adds correlation and local idempotency metadata. */
class IdempotencyHeaderBuilder implements BuilderInterface
{
    public function __construct(
        private readonly CorrelationIdProvider $correlationId,
        private readonly string $command
    ) {
    }

    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder();
        $orderId = (string) ($order?->getOrderIncrementId() ?? 'unknown');
        $payment = $paymentDO->getPayment();
        $attempt = (int) ($payment->getAdditionalInformation('gtstudio_attempt') ?? 0) + 1;
        $payment->setAdditionalInformation('gtstudio_attempt', $attempt);

        $idempotencyKey = sprintf('%s:%s:%d', $orderId, $this->command, $attempt);

        return [
            '__headers' => [
                'idempotency_key' => $idempotencyKey,
                'correlation_id' => $this->correlationId->get(),
                'store_id' => $order?->getStoreId(),
            ],
        ];
    }
}
