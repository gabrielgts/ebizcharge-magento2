<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

/**
 * Vault flow for ACH — same shape as VaultDataBuilder (split "<custNum>:<methodId>" gateway token,
 * route to runCustomerTransaction) but for the ACH command pool.
 *
 * Kept separate so vault wiring stays explicit per method-type; one builder per concept beats
 * conditional logic.
 */
class AchVaultDataBuilder implements BuilderInterface
{
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        if (!$payment instanceof OrderPaymentInterface) {
            return [];
        }

        $extension = $payment->getExtensionAttributes();
        $token = $extension !== null ? $extension->getVaultPaymentToken() : null;
        if ($token === null) {
            return [];
        }

        $gatewayToken = (string) $token->getGatewayToken();
        if (!str_contains($gatewayToken, ':')) {
            return [];
        }
        [$custNum, $methodId] = explode(':', $gatewayToken, 2);
        if ($custNum === '' || $methodId === '') {
            return [];
        }

        return [
            'customerNumber' => (int) $custNum,
            'paymentMethodId' => (int) $methodId,
            '__method' => 'runCustomerTransaction',
        ];
    }
}
