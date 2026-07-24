<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Plugin\Vault;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Service\CardProfileDeleter;
use Gtstudio\Ebizcharge\Service\CorrelationIdProvider;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

/** Best-effort remote deletion before Magento removes its local Vault token. */
class DeleteStoredCardPlugin
{
    public function __construct(
        private readonly CardProfileDeleter $deleter,
        private readonly Logger $logger,
        private readonly CorrelationIdProvider $correlationId
    ) {
    }

    public function beforeDelete(
        PaymentTokenRepositoryInterface $subject,
        PaymentTokenInterface $paymentToken
    ): ?array {
        if ($paymentToken->getPaymentMethodCode() !== Config::METHOD_CODE) {
            return null;
        }

        try {
            $this->deleter->delete(
                (string) $paymentToken->getGatewayToken(),
                $paymentToken->getWebsiteId() === null ? null : (int) $paymentToken->getWebsiteId()
            );
        } catch (\Throwable $e) {
            // Local deletion must remain available even when EBizCharge is unavailable.
            $this->logger->warning('vault.remote_delete_failed', [
                'correlation_id' => $this->correlationId->get(),
                'reason' => $e::class,
            ]);
        }

        return null;
    }
}
