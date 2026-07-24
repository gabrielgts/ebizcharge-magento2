<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Request;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Service\CustomerIdentityManager;
use Gtstudio\Ebizcharge\Service\CustomerIdentityMetadata;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderPaymentInterface;

/**
 * Builds the runCustomerTransaction body for vault-paid orders.
 *
 * The gateway_token stored on the vault is "<CustNum>:<MethodID>". We split it back into the
 * two ids EBizCharge needs and route the call to the runCustomerTransaction SOAP method (no PAN).
 *
 * Wired into the vault command pool — replaces PaymentDataBuilder for vault payments.
 */
class VaultDataBuilder implements BuilderInterface
{
    public function __construct(private readonly CustomerIdentityManager $identityManager)
    {
    }

    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        if (!$payment instanceof OrderPaymentInterface) {
            throw new LocalizedException(__('Saved payment information is unavailable.'));
        }

        $extension = $payment->getExtensionAttributes();
        $token = $extension !== null ? $extension->getVaultPaymentToken() : null;
        if ($token === null) {
            throw new LocalizedException(__('Saved payment information is unavailable.'));
        }
        if (!$token->getIsActive() || $token->getPaymentMethodCode() !== Config::METHOD_CODE) {
            throw new LocalizedException(__('The saved card is no longer available.'));
        }

        $orderCustomerId = (int) ($payment->getOrder()?->getCustomerId() ?? 0);
        $tokenCustomerId = (int) ($token->getCustomerId() ?? 0);
        if ($orderCustomerId <= 0 || $tokenCustomerId <= 0 || $orderCustomerId !== $tokenCustomerId) {
            throw new LocalizedException(__('The saved card is not available for this customer.'));
        }

        $gatewayToken = (string) $token->getGatewayToken();
        [$custNum, $methodId] = $this->parseToken($gatewayToken);
        if ($custNum === '' || $methodId === '') {
            throw new LocalizedException(__('The saved card information is invalid.'));
        }

        $payment->setAdditionalInformation(CustomerIdentityMetadata::CUSTOMER_NUMBER, $custNum);
        try {
            $identity = $this->identityManager->getLocal($orderCustomerId);
            $payment->setAdditionalInformation(CustomerIdentityMetadata::CUSTOMER_ID, $identity->customerId);
            $payment->setAdditionalInformation(CustomerIdentityMetadata::STATUS, $identity->status);
            if ($identity->customerInternalId !== '') {
                $payment->setAdditionalInformation(
                    CustomerIdentityMetadata::CUSTOMER_INTERNAL_ID,
                    $identity->customerInternalId
                );
            }
        } catch (\Throwable) {
            // The Vault token is sufficient for runCustomerTransaction. Local mapping health must
            // never turn a valid saved-card payment into a different gateway command or payload.
            $payment->setAdditionalInformation(CustomerIdentityMetadata::STATUS, 'unverified');
        }

        return [
            'custNum' => $custNum,
            'paymentMethodID' => $methodId,
            '__method' => 'runCustomerTransaction',
            'tran' => [
                'isRecurring' => (bool) $payment->getAdditionalInformation('gtstudio_recurring_charge'),
                'InventoryLocation' => '',
                'IgnoreDuplicate' => false,
            ],
        ];
    }

    /** @return array{0:string,1:string} */
    private function parseToken(string $gatewayToken): array
    {
        if (!str_contains($gatewayToken, ':')) {
            return ['', ''];
        }
        [$custNum, $methodId] = explode(':', $gatewayToken, 2);
        return [trim($custNum), trim($methodId)];
    }
}
