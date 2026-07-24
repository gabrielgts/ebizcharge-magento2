<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\ViewModel\Customer;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionRepositoryInterface;
use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;

/**
 * Powers the My Account → Subscriptions list page.
 *
 * Pulls subscriptions for the logged-in customer and a flat list of payment-method choices for
 * the "update payment method" inline form.
 */
class SubscriptionList implements ArgumentInterface
{
    private const COLORS = [
        SubscriptionInterface::STATUS_ACTIVE => '#79a22e',
        SubscriptionInterface::STATUS_PAUSED => '#1979c3',
        SubscriptionInterface::STATUS_FAILING => '#e02b27',
        SubscriptionInterface::STATUS_CANCELLED => '#7d7d7d',
        SubscriptionInterface::STATUS_EXPIRED => '#7d7d7d',
        SubscriptionInterface::STATUS_COMPLETED => '#b8a300',
    ];

    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly PaymentTokenManagementInterface $tokenManagement
    ) {
    }

    /** @return SubscriptionInterface[] */
    public function getSubscriptions(): array
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        if ($customerId === 0) {
            return [];
        }
        $criteria = $this->searchCriteriaBuilder
            ->addFilter(SubscriptionInterface::CUSTOMER_ID, $customerId)
            ->addSortOrder(
                $this->sortOrderBuilder
                    ->setField(SubscriptionInterface::CREATED_AT)
                    ->setDirection('DESC')
                    ->create()
            )
            ->create();
        return $this->subscriptionRepository->getList($criteria)->getItems();
    }

    /** @return PaymentTokenInterface[] */
    public function getCustomerPaymentTokens(): array
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        if ($customerId === 0) {
            return [];
        }
        $tokens = $this->tokenManagement->getVisibleAvailableTokens($customerId);
        if ($tokens === null) {
            return [];
        }
        return array_values(array_filter(
            $tokens,
            static fn (PaymentTokenInterface $token): bool =>
                $token->getPaymentMethodCode() === Config::METHOD_CODE
        ));
    }

    public function getStatusColor(string $status): string
    {
        return self::COLORS[$status] ?? '#666';
    }

    public function getStatusLabel(string $status): string
    {
        return ucfirst($status);
    }

    public function isCancellable(SubscriptionInterface $subscription): bool
    {
        return !in_array(
            $subscription->getStatus(),
            [
                SubscriptionInterface::STATUS_CANCELLED,
                SubscriptionInterface::STATUS_COMPLETED,
                SubscriptionInterface::STATUS_EXPIRED,
            ],
            true
        );
    }

    public function isPaused(SubscriptionInterface $subscription): bool
    {
        return in_array(
            $subscription->getStatus(),
            [SubscriptionInterface::STATUS_PAUSED, SubscriptionInterface::STATUS_FAILING],
            true
        );
    }

    public function isFailing(SubscriptionInterface $subscription): bool
    {
        return $subscription->getStatus() === SubscriptionInterface::STATUS_FAILING;
    }

    public function getTokenLabel(PaymentTokenInterface $token): string
    {
        $details = $token->getTokenDetails();
        if ($details === null) {
            return (string) $token->getPublicHash();
        }
        $decoded = json_decode((string) $details, true);
        if (!is_array($decoded)) {
            return (string) $token->getPublicHash();
        }
        if (isset($decoded['type'], $decoded['maskedCC'])) {
            return sprintf('%s •••• %s', (string) $decoded['type'], (string) $decoded['maskedCC']);
        }
        if (isset($decoded['accountType'], $decoded['maskedAccount'])) {
            return sprintf('%s •••• %s', (string) $decoded['accountType'], (string) $decoded['maskedAccount']);
        }
        return (string) $token->getPublicHash();
    }
}
