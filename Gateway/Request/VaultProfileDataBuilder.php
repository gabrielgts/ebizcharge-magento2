<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Request;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Service\CustomerIdentityManager;
use Magento\Payment\Gateway\Data\AddressAdapterInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;

/** Builds non-sensitive metadata used to provision an EBizCharge profile after approval. */
class VaultProfileDataBuilder implements BuilderInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly CustomerIdentityManager $identityManager
    ) {
    }

    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        if (!(bool) $payment->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE)) {
            return [];
        }

        $order = $paymentDO->getOrder();
        $customerId = (int) ($order?->getCustomerId() ?? 0);
        if ($customerId <= 0) {
            return [];
        }
        try {
            $identity = $this->identityManager->getLocal($customerId);
        } catch (\Throwable) {
            $identity = null;
        }
        $ebizCustomerId = $identity?->customerId ?? (string) $customerId;

        $billing = $order?->getBillingAddress();
        $holder = trim(sprintf(
            '%s %s',
            (string) ($billing?->getFirstname() ?? ''),
            (string) ($billing?->getLastname() ?? '')
        ));
        $street = $this->street($billing);
        $storeId = $order?->getStoreId();
        $storeId = $storeId === null ? null : (int) $storeId;

        return [
            '__vault_profile' => [
                'magento_customer_id' => $customerId,
                'customer' => array_filter([
                    'CustomerId' => $ebizCustomerId,
                    'FirstName' => (string) ($billing?->getFirstname() ?? ''),
                    'LastName' => (string) ($billing?->getLastname() ?? ''),
                    'CompanyName' => (string) ($billing?->getCompany() ?? ''),
                    'Phone' => (string) ($billing?->getTelephone() ?? ''),
                    'Email' => (string) ($billing?->getEmail() ?? ''),
                    'SoftwareId' => $this->config->getSoftwareTag($storeId),
                    'BillingAddress' => $this->customerAddress($billing),
                ], static fn (mixed $value): bool => $value !== '' && $value !== []),
                'payment_method' => array_filter([
                    'MethodType' => 'CreditCard',
                    'MethodName' => substr(
                        'Magento ' . (string) ($order?->getOrderIncrementId() ?? $customerId),
                        0,
                        64
                    ),
                    'AccountHolderName' => $holder,
                    'AvsStreet' => $street,
                    'AvsZip' => (string) ($billing?->getPostcode() ?? ''),
                ], static fn (mixed $value): bool => $value !== ''),
                'customer_internal_id' => $identity?->customerInternalId ?? '',
                'cust_num' => $identity?->customerNumber ?? '',
            ],
        ];
    }

    private function street(?AddressAdapterInterface $address): string
    {
        if ($address === null) {
            return '';
        }
        return trim(implode(' ', array_filter([
            (string) $address->getStreetLine1(),
            (string) $address->getStreetLine2(),
        ])));
    }

    /** @return array<string,mixed> */
    private function customerAddress(?AddressAdapterInterface $address): array
    {
        if ($address === null) {
            return [];
        }

        return array_filter([
            'FirstName' => (string) $address->getFirstname(),
            'LastName' => (string) $address->getLastname(),
            'CompanyName' => (string) $address->getCompany(),
            'Address1' => (string) $address->getStreetLine1(),
            'Address2' => (string) $address->getStreetLine2(),
            'City' => (string) $address->getCity(),
            'State' => (string) $address->getRegionCode(),
            'ZipCode' => (string) $address->getPostcode(),
            'Country' => (string) $address->getCountryId(),
            'IsDefault' => true,
        ], static fn (mixed $value): bool => $value !== '');
    }
}
