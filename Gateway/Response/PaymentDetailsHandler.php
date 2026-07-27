<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Response;

use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Service\CorrelationIdProvider;
use Gtstudio\Ebizcharge\Service\CustomerIdentityManager;
use Gtstudio\Ebizcharge\Service\CustomerIdentityMetadata;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class PaymentDetailsHandler implements HandlerInterface
{
    public function __construct(
        private readonly CustomerIdentityManager $identityManager,
        private readonly Logger $logger,
        private readonly CorrelationIdProvider $correlationId
    ) {
    }

    public function handle(array $handlingSubject, array $response): void
    {
        $payment = SubjectReader::readPayment($handlingSubject)->getPayment();

        if (isset($response['AuthCode'])) {
            $payment->setCcApproval((string) $response['AuthCode']);
        }
        if (isset($response['AvsResultCode'])) {
            $payment->setCcAvsStatus((string) $response['AvsResultCode']);
        }
        if (isset($response['CardCodeResultCode'])) {
            $payment->setCcCidStatus((string) $response['CardCodeResultCode']);
        }
        if (isset($response['BatchNum'])) {
            $payment->setAdditionalInformation('gtstudio_batch_num', (string) $response['BatchNum']);
        }
        if (isset($response['BatchRefNum'])) {
            $payment->setAdditionalInformation('gtstudio_batch_ref_num', (string) $response['BatchRefNum']);
        }
        if (isset($response['ResultCode'])) {
            $payment->setAdditionalInformation('gtstudio_result_code', (string) $response['ResultCode']);
        }

        $customerNumber = trim((string) ($response['CustNum'] ?? ''));
        if ($customerNumber === '' || $customerNumber === '0') {
            return;
        }

        $payment->setAdditionalInformation(CustomerIdentityMetadata::CUSTOMER_NUMBER, $customerNumber);
        $customerId = (int) ($payment->getOrder()?->getCustomerId() ?? 0);
        if ($customerId <= 0) {
            return;
        }

        try {
            $this->identityManager->recordCustomerNumber($customerId, $customerNumber);
        } catch (\Throwable $e) {
            // Mapping persistence must not fail an approved gateway transaction.
            $this->logger->warning('customer_identity.response_persist_failed', [
                'correlation_id' => $this->correlationId->get(),
                'magento_customer_id' => $customerId,
                'reason' => $e::class,
            ]);
        }
    }
}
