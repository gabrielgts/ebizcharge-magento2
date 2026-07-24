<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Request;

use Magento\Payment\Gateway\Data\AddressAdapterInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class AddressBuilder implements BuilderInterface
{
    public function build(array $buildSubject): array
    {
        $payment = SubjectReader::readPayment($buildSubject);
        $order = $payment->getOrder();

        return [
            'tran' => array_filter([
                'BillingAddress' => $this->mapAddress($order?->getBillingAddress()),
                'ShippingAddress' => $this->mapAddress($order?->getShippingAddress()),
            ]),
        ];
    }

    /** @return array<string,string>|null */
    private function mapAddress(?AddressAdapterInterface $address): ?array
    {
        if ($address === null) {
            return null;
        }

        return array_filter([
            'FirstName' => (string) $address->getFirstname(),
            'LastName' => (string) $address->getLastname(),
            'Company' => (string) $address->getCompany(),
            'Street' => trim(implode(' ', array_filter([
                (string) $address->getStreetLine1(),
                (string) $address->getStreetLine2(),
            ]))),
            'City' => (string) $address->getCity(),
            'State' => (string) $address->getRegionCode(),
            'Zip' => (string) $address->getPostcode(),
            'Country' => (string) $address->getCountryId(),
            'Phone' => (string) $address->getTelephone(),
            'Email' => (string) $address->getEmail(),
        ], static fn ($v) => $v !== '' && $v !== null);
    }
}
