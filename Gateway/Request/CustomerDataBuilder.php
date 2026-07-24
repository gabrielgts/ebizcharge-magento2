<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Request;

use Gtstudio\Ebizcharge\Service\CustomerIdentity;
use Gtstudio\Ebizcharge\Service\CustomerIdentityManager;
use Gtstudio\Ebizcharge\Service\CustomerIdentityMetadata;
use Gtstudio\Ebizcharge\Service\CustomerPayloadBuilder;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;

class CustomerDataBuilder implements BuilderInterface
{
    public function __construct(
        private readonly CustomerIdentityManager $identityManager,
        private readonly CustomerPayloadBuilder $payloadBuilder
    ) {
    }

    public function build(array $buildSubject): array
    {
        $payment = SubjectReader::readPayment($buildSubject);
        $order = $payment->getOrder();
        $billing = $order?->getBillingAddress();

        $customerId = $order?->getCustomerId();
        $identity = null;
        if ($order !== null && $customerId !== null && (int) $customerId > 0) {
            $storeId = $order->getStoreId();
            $identity = $this->identityManager->resolveForTransaction(
                (int) $customerId,
                $this->payloadBuilder->fromOrder($order),
                $storeId === null ? null : (int) $storeId
            );
            $this->persistMetadata($payment->getPayment(), $identity);
        }
        $customerIdString = $identity?->customerId ?? 'Guest';

        return [
            'tran' => [
                'CustomerID' => $customerIdString,
                'AccountHolder' => trim(sprintf(
                    '%s %s',
                    (string) ($billing?->getFirstname() ?? ''),
                    (string) ($billing?->getLastname() ?? '')
                )),
            ],
        ];
    }

    private function persistMetadata(InfoInterface $payment, CustomerIdentity $identity): void
    {
        $payment->setAdditionalInformation(CustomerIdentityMetadata::CUSTOMER_ID, $identity->customerId);
        $payment->setAdditionalInformation(CustomerIdentityMetadata::STATUS, $identity->status);
        if ($identity->customerInternalId !== '') {
            $payment->setAdditionalInformation(
                CustomerIdentityMetadata::CUSTOMER_INTERNAL_ID,
                $identity->customerInternalId
            );
        }
        if ($identity->customerNumber !== '') {
            $payment->setAdditionalInformation(
                CustomerIdentityMetadata::CUSTOMER_NUMBER,
                $identity->customerNumber
            );
        }
    }
}
