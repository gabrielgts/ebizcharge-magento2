<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

use Magento\Customer\Api\Data\AddressInterface as CustomerAddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Payment\Gateway\Data\AddressAdapterInterface;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;

/** Builds the non-payment customer fields accepted by EBizCharge AddCustomer. */
class CustomerPayloadBuilder
{
    /** @return array<string,mixed> */
    public function fromOrder(OrderAdapterInterface $order): array
    {
        $billing = $order->getBillingAddress();

        return array_filter([
            'FirstName' => (string) ($billing?->getFirstname() ?? ''),
            'LastName' => (string) ($billing?->getLastname() ?? ''),
            'CompanyName' => (string) ($billing?->getCompany() ?? ''),
            'Phone' => (string) ($billing?->getTelephone() ?? ''),
            'Email' => (string) ($billing?->getEmail() ?? ''),
            'BillingAddress' => $this->gatewayAddress($billing),
        ], $this->notEmpty(...));
    }

    /** @return array<string,mixed> */
    public function fromCustomer(CustomerInterface $customer): array
    {
        $billing = null;
        $defaultBillingId = (string) ($customer->getDefaultBilling() ?? '');
        foreach ($customer->getAddresses() as $address) {
            if ((string) $address->getId() === $defaultBillingId) {
                $billing = $address;
                break;
            }
        }

        return array_filter([
            'FirstName' => (string) $customer->getFirstname(),
            'LastName' => (string) $customer->getLastname(),
            'Email' => (string) $customer->getEmail(),
            'BillingAddress' => $this->customerAddress($billing),
        ], self::notEmpty(...));
    }

    /** @return array<string,mixed> */
    private function gatewayAddress(?AddressAdapterInterface $address): array
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
        ], self::notEmpty(...));
    }

    /** @return array<string,mixed> */
    private function customerAddress(?CustomerAddressInterface $address): array
    {
        if ($address === null) {
            return [];
        }

        $street = $address->getStreet();
        return array_filter([
            'FirstName' => (string) $address->getFirstname(),
            'LastName' => (string) $address->getLastname(),
            'CompanyName' => (string) ($address->getCompany() ?? ''),
            'Address1' => (string) ($street[0] ?? ''),
            'Address2' => (string) ($street[1] ?? ''),
            'City' => (string) $address->getCity(),
            'State' => (string) ($address->getRegion()?->getRegionCode() ?? ''),
            'ZipCode' => (string) $address->getPostcode(),
            'Country' => (string) $address->getCountryId(),
            'IsDefault' => true,
        ], self::notEmpty(...));
    }

    private function notEmpty(mixed $value): bool
    {
        return $value !== '' && $value !== [] && $value !== null;
    }
}
