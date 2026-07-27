<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionChargeInterface;
use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Logger\Logger;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/** Sends subscription lifecycle notifications. */
class SubscriptionEmailNotifier
{
    public const TEMPLATE_FAILED = 'gtstudio_ebizcharge_subscription_charge_failed';
    public const TEMPLATE_UPCOMING = 'gtstudio_ebizcharge_subscription_upcoming_charge';
    public const TEMPLATE_CARD_EXPIRING = 'gtstudio_ebizcharge_subscription_card_expiring';

    private const SENDER_IDENTITY_PATH = 'sales_email/order/identity';

    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $urlBuilder,
        private readonly Logger $logger
    ) {
    }

    public function notifyChargeFailed(SubscriptionInterface $subscription, SubscriptionChargeInterface $charge): void
    {
        $this->send(
            self::TEMPLATE_FAILED,
            $subscription,
            [
                'subscription' => $this->subscriptionVars($subscription),
                'charge' => [
                    'error_message' => (string) ($charge->getErrorMessage() ?? ''),
                ],
                'update_payment_url' => $this->subscriptionUrl($subscription, 'index'),
                'subscription_view_url' => $this->subscriptionUrl($subscription, 'view'),
            ],
            'charge_failed'
        );
    }

    public function notifyUpcomingCharge(SubscriptionInterface $subscription): void
    {
        $this->send(
            self::TEMPLATE_UPCOMING,
            $subscription,
            [
                'subscription' => $this->subscriptionVars($subscription),
                'subscription_view_url' => $this->subscriptionUrl($subscription, 'view'),
            ],
            'upcoming_charge'
        );
    }

    public function notifyCardExpiring(SubscriptionInterface $subscription, PaymentTokenInterface $token): void
    {
        $details = $this->decodeTokenDetails($token);
        $this->send(
            self::TEMPLATE_CARD_EXPIRING,
            $subscription,
            [
                'subscription' => $this->subscriptionVars($subscription),
                'token' => [
                    'maskedCC' => (string) ($details['maskedCC'] ?? $details['maskedAccount'] ?? '****'),
                    'type' => (string) ($details['type'] ?? $details['accountType'] ?? ''),
                    'expiration' => (string) ($details['expirationDate'] ?? $token->getExpiresAt() ?? ''),
                ],
                'update_payment_url' => $this->subscriptionUrl($subscription, 'index'),
            ],
            'card_expiring'
        );
    }

    private function send(
        string $templateId,
        SubscriptionInterface $subscription,
        array $vars,
        string $eventLabel
    ): void {
        try {
            $customer = $this->customerRepository->getById($subscription->getCustomerId());
        } catch (\Throwable $e) {
            $this->logger->warning('subscription.email.skip', [
                'reason' => 'customer_not_found',
                'subscription_id' => $subscription->getEntityId(),
                'message' => $e->getMessage(),
            ]);
            return;
        }

        $storeId = $subscription->getStoreId();

        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions([
                    'area' => Area::AREA_FRONTEND,
                    'store' => $storeId,
                ])
                ->setTemplateVars($vars)
                ->setFromByScope(
                    $this->scopeConfig->getValue(
                        self::SENDER_IDENTITY_PATH,
                        ScopeInterface::SCOPE_STORE,
                        $storeId
                    ),
                    $storeId
                )
                ->addTo(
                    $customer->getEmail(),
                    trim(($customer->getFirstname() ?? '') . ' ' . ($customer->getLastname() ?? ''))
                )
                ->getTransport();
            $transport->sendMessage();

            $this->logger->info('subscription.email.sent', [
                'event' => $eventLabel,
                'template' => $templateId,
                'subscription_id' => $subscription->getEntityId(),
                'customer_id' => $subscription->getCustomerId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('subscription.email.failed', [
                'event' => $eventLabel,
                'template' => $templateId,
                'subscription_id' => $subscription->getEntityId(),
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function subscriptionVars(SubscriptionInterface $subscription): array
    {
        return [
            'label' => (string) ($subscription->getLabel() ?? '#' . $subscription->getEntityId()),
            'amount' => number_format($subscription->getAmount(), 2),
            'currency' => $subscription->getCurrencyCode(),
            'next_bill_date' => $subscription->getNextBillDate(),
            'frequency' => $subscription->getFrequency(),
        ];
    }

    private function subscriptionUrl(SubscriptionInterface $subscription, string $action): string
    {
        try {
            $store = $this->storeManager->getStore($subscription->getStoreId());
        } catch (\Throwable) {
            $store = $this->storeManager->getDefaultStoreView();
        }

        $params = ['_secure' => true];
        if ($action === 'view') {
            $params['id'] = $subscription->getEntityId();
        }
        if ($store !== null) {
            $params['_scope'] = $store->getId();
        }

        return $this->urlBuilder->getUrl('gtstudio_ebizcharge/subscription/' . $action, $params);
    }

    /** @return array<string,mixed> */
    private function decodeTokenDetails(PaymentTokenInterface $token): array
    {
        $raw = (string) $token->getTokenDetails();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
