<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Response;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Gateway\Request\AchDataBuilder;
use Gtstudio\Ebizcharge\Logger\Logger;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\VaultConfigProvider;

/**
 * Builds a Magento\Vault payment token from the EBizCharge transaction response when the customer
 * asked us to save the card or bank account.
 *
 * Token storage:
 *  - gateway_token  = "<CustNum>:<MethodID>" — both ids needed to call runCustomerTransaction
 *  - details        = JSON containing brand/account-type, last4, (cards) expiration. NEVER PAN/account
 *  - public_hash    = computed by Magento_Vault from gateway_token + customer_id
 *
 * Branches by token type: TOKEN_TYPE_CREDIT_CARD for cards, TOKEN_TYPE_ACCOUNT for ACH.
 */
class VaultDetailsHandler implements HandlerInterface
{
    public function __construct(
        private readonly PaymentTokenFactoryInterface $tokenFactory,
        private readonly OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory,
        private readonly Logger $logger
    ) {
    }

    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        if (!$payment instanceof OrderPaymentInterface) {
            return;
        }

        $shouldSave = (bool) $payment->getAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE);
        if (!$shouldSave) {
            return;
        }

        $vaultStatus = (string) ($response['VaultSaveStatus'] ?? '');
        if ($vaultStatus === 'failed') {
            $payment->setAdditionalInformation('vault_save_status', 'failed');
            return;
        }

        $custNum = (string) ($response['CustNum'] ?? '');
        $methodId = (string) ($response['CustomerPaymentMethodId'] ?? $response['PaymentMethodID'] ?? '');

        if ($custNum === '' || $methodId === '') {
            $payment->setAdditionalInformation('vault_save_status', 'failed');
            $this->logger->info('vault.skip_token_creation', [
                'reason' => 'gateway_response_missing_ids',
                'has_cust_num' => $custNum !== '',
                'has_method_id' => $methodId !== '',
            ]);
            return;
        }

        $token = $this->isAchPayment($payment)
            ? $this->createAchToken($payment, $custNum, $methodId)
            : $this->createCardToken($payment, $custNum, $methodId);

        if ($token === null) {
            return;
        }

        $extension = $payment->getExtensionAttributes() ?: $this->paymentExtensionFactory->create();
        $extension->setVaultPaymentToken($token);
        $payment->setExtensionAttributes($extension);

        $this->logger->info('vault.token_created', [
            'type' => $token->getType(),
            'cust_num' => $custNum,
            'method_id' => $methodId,
            'expiration' => $token->getExpiresAt(),
        ]);
    }

    private function isAchPayment(OrderPaymentInterface $payment): bool
    {
        return $payment->getMethod() === Config::METHOD_CODE_ACH
            || $payment->getAdditionalInformation('ach_last4') !== null;
    }

    private function createCardToken(OrderPaymentInterface $payment, string $custNum, string $methodId): ?PaymentTokenInterface
    {
        $expMonth = str_pad((string) $payment->getCcExpMonth(), 2, '0', STR_PAD_LEFT);
        $expYear = (string) $payment->getCcExpYear();
        if ($expMonth === '' || $expMonth === '00' || $expYear === '') {
            return null;
        }
        if (strlen($expYear) === 2) {
            $expYear = '20' . $expYear;
        }

        $expiresAt = sprintf('%s-%s-01 00:00:00', $expYear, $expMonth);

        $details = [
            'type' => (string) $payment->getCcType(),
            'maskedCC' => (string) $payment->getCcLast4(),
            'expirationDate' => $expMonth . '/' . $expYear,
        ];

        $token = $this->tokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
        $token->setGatewayToken($custNum . ':' . $methodId);
        $token->setExpiresAt($this->endOfMonth($expiresAt));
        $token->setTokenDetails((string) json_encode($details));
        return $token;
    }

    private function createAchToken(OrderPaymentInterface $payment, string $custNum, string $methodId): PaymentTokenInterface
    {
        $accountType = (string) $payment->getAdditionalInformation(AchDataBuilder::KEY_ACCOUNT_TYPE);
        $details = [
            'accountType' => ucfirst($accountType !== '' ? $accountType : 'checking'),
            'maskedAccount' => (string) $payment->getAdditionalInformation('ach_last4'),
        ];

        $token = $this->tokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_ACCOUNT);
        $token->setGatewayToken($custNum . ':' . $methodId);
        // ACH bank accounts have no expiration; pick a far-future sentinel so Magento_Vault doesn't
        // expire them and so saved_payment_methods listing keeps showing them.
        $token->setExpiresAt(date('Y-m-d H:i:s', strtotime('+10 years')));
        $token->setTokenDetails((string) json_encode($details));
        return $token;
    }

    /**
     * Vault expects expires_at in the future; pin to last second of the expiration month.
     */
    private function endOfMonth(string $startOfMonth): string
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startOfMonth);
        if ($dt === false) {
            return $startOfMonth;
        }
        return $dt->modify('+1 month -1 second')->format('Y-m-d H:i:s');
    }
}
