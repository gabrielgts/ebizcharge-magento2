<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

use Gtstudio\Ebizcharge\Gateway\Http\SoapMethodClient;

/** Provisions the EBizCharge customer and card profile after an approved PAN transaction. */
class CardProfileProvisioner
{
    public function __construct(
        private readonly SoapMethodClient $soap,
        private readonly CustomerIdentityManager $identityManager
    ) {
    }

    /**
     * @param array<string,mixed> $transactionRequest
     * @param array<string,mixed> $metadata
     * @param array<string,mixed> $headers
     * @return array{cust_num:string,method_id:string}
     */
    public function provision(array $transactionRequest, array $metadata, array $headers = []): array
    {
        $securityToken = $transactionRequest['securityToken'] ?? null;
        $customer = $metadata['customer'] ?? null;
        $profile = $metadata['payment_method'] ?? null;
        $cardData = $transactionRequest['tran']['CreditCardData'] ?? null;

        if (!is_array($securityToken) || !is_array($customer) || !is_array($profile) || !is_array($cardData)) {
            throw new \RuntimeException('Vault profile request is incomplete.');
        }

        $customerId = trim((string) ($customer['CustomerId'] ?? ''));
        if ($customerId === '') {
            throw new \RuntimeException('Vault customer identifier is missing.');
        }

        $profile += [
            'CardNumber' => (string) ($cardData['CardNumber'] ?? ''),
            'CardExpiration' => (string) ($cardData['CardExpiration'] ?? ''),
            'CardCode' => (string) ($cardData['CardCode'] ?? ''),
        ];
        foreach (['AccountHolderName', 'AvsZip', 'CardExpiration', 'CardNumber'] as $required) {
            if (trim((string) ($profile[$required] ?? '')) === '') {
                throw new \RuntimeException('Vault payment profile is incomplete.');
            }
        }
        // EBizCharge asks integrations to omit optional fields rather than serialize empty values.
        // CVV is request-local and may legitimately be absent for flows where Magento does not collect it.
        $profile = array_filter(
            $profile,
            static fn (mixed $value): bool => $value !== '' && $value !== null
        );

        $internalId = trim((string) ($metadata['customer_internal_id'] ?? ''));
        $custNum = trim((string) ($metadata['cust_num'] ?? ''));
        if ($internalId === '' || $custNum === '') {
            $magentoCustomerId = (int) ($metadata['magento_customer_id'] ?? 0);
            if ($magentoCustomerId <= 0) {
                throw new \RuntimeException('Magento Vault customer identifier is missing.');
            }
            $identity = $this->identityManager->sync(
                $magentoCustomerId,
                $customer,
                isset($headers['store_id']) ? (int) $headers['store_id'] : null
            );
            $internalId = $identity->customerInternalId;
            $custNum = $identity->customerNumber;
        }

        $methodResponse = $this->soap->call('AddCustomerPaymentMethodProfile', [
            'securityToken' => $securityToken,
            'customerInternalId' => $internalId,
            'paymentMethodProfile' => $profile,
        ], $headers);
        $methodId = $this->scalarResult($methodResponse, 'AddCustomerPaymentMethodProfileResult');

        return ['cust_num' => $custNum, 'method_id' => $methodId];
    }

    /** @param array<string,mixed> $response */
    private function scalarResult(array $response, string $key): string
    {
        $value = $response[$key] ?? null;
        if (is_scalar($value) && trim((string) $value) !== '') {
            return trim((string) $value);
        }
        throw new \RuntimeException('EBizCharge profile response is malformed.');
    }
}
