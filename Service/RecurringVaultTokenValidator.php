<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

/** Validates that a subscription can access an active EBizCharge card token. */
class RecurringVaultTokenValidator
{
    public function __construct(
        private readonly PaymentTokenRepositoryInterface $tokenRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function validate(SubscriptionInterface $subscription, ?int $tokenId = null): PaymentTokenInterface
    {
        $tokenId ??= $subscription->getVaultPaymentTokenId();
        if ($tokenId === null || $tokenId <= 0) {
            throw new LocalizedException(__('Subscription has no payment method on file.'));
        }

        $token = $this->tokenRepository->getById($tokenId);
        if ((int) $token->getEntityId() !== $tokenId
            || (int) $token->getCustomerId() !== $subscription->getCustomerId()
            || !$token->getIsActive()
            || !$token->getIsVisible()
            || $token->getPaymentMethodCode() !== Config::METHOD_CODE
            || trim((string) $token->getPublicHash()) === ''
            || !str_contains((string) $token->getGatewayToken(), ':')
        ) {
            throw new LocalizedException(__('The subscription payment method is not available.'));
        }

        $this->validateWebsite($subscription, $token);
        $this->validateExpiration($token);
        return $token;
    }

    private function validateWebsite(
        SubscriptionInterface $subscription,
        PaymentTokenInterface $token
    ): void {
        $tokenWebsiteId = (int) ($token->getWebsiteId() ?? 0);
        if ($tokenWebsiteId <= 0) {
            return;
        }
        try {
            $subscriptionWebsiteId = (int) $this->storeManager
                ->getStore($subscription->getStoreId())
                ->getWebsiteId();
        } catch (\Throwable) {
            throw new LocalizedException(__('The subscription store is not available.'));
        }
        if ($subscriptionWebsiteId !== $tokenWebsiteId) {
            throw new LocalizedException(__('The subscription payment method is not available for this website.'));
        }
    }

    private function validateExpiration(PaymentTokenInterface $token): void
    {
        $expiresAt = trim((string) ($token->getExpiresAt() ?? ''));
        if ($expiresAt === '') {
            throw new LocalizedException(__('The subscription payment method has no expiration date.'));
        }
        try {
            $expiration = new \DateTimeImmutable($expiresAt);
        } catch (\Throwable) {
            throw new LocalizedException(__('The subscription payment method expiration is invalid.'));
        }
        if ($expiration <= new \DateTimeImmutable('now')) {
            throw new LocalizedException(__('The subscription payment method has expired.'));
        }
    }
}
