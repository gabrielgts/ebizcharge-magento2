<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Block\Customer;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionRepositoryInterface;
use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Phrase;
use Magento\Framework\View\Element\Template;
use Magento\Payment\Model\CcConfigProvider;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractCardRenderer;

class CardRenderer extends AbstractCardRenderer
{
    private const PAYMENT_SERVICES_CARD_LIST_TEMPLATE =
        'Magento_PaymentServicesPaypal::customer_account/vault/list/cards_list.phtml';

    private const RENEWABLE_SUBSCRIPTION_STATUSES = [
        SubscriptionInterface::STATUS_ACTIVE,
        SubscriptionInterface::STATUS_PAUSED,
        SubscriptionInterface::STATUS_FAILING,
    ];

    /** @var array<int,bool> */
    private array $subscriptionUsage = [];

    public function __construct(
        Template\Context $context,
        CcConfigProvider $iconsProvider,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        array $data = []
    ) {
        parent::__construct($context, $iconsProvider, $data);
    }

    public function canRender(PaymentTokenInterface $token): bool
    {
        return $token->getPaymentMethodCode() === Config::METHOD_CODE;
    }

    public function getNumberLast4Digits(): string
    {
        return (string) ($this->getTokenDetails()['maskedCC'] ?? '');
    }

    public function getExpDate(): string
    {
        return (string) ($this->getTokenDetails()['expirationDate'] ?? '');
    }

    public function getIconUrl(): string
    {
        return (string) $this->cardIcon()['url'];
    }

    public function getIconHeight(): int
    {
        return (int) $this->cardIcon()['height'];
    }

    public function getIconWidth(): int
    {
        return (int) $this->cardIcon()['width'];
    }

    public function usesDescriptionColumn(): bool
    {
        $parent = $this->getParentBlock();
        return $parent instanceof Template
            && $parent->getTemplate() === self::PAYMENT_SERVICES_CARD_LIST_TEMPLATE;
    }

    public function isUsedByRenewableSubscription(PaymentTokenInterface $token): bool
    {
        $tokenId = (int) $token->getEntityId();
        if ($tokenId <= 0) {
            return false;
        }
        if (array_key_exists($tokenId, $this->subscriptionUsage)) {
            return $this->subscriptionUsage[$tokenId];
        }

        try {
            $criteria = $this->searchCriteriaBuilder
                ->addFilter(SubscriptionInterface::VAULT_PAYMENT_TOKEN_ID, $tokenId)
                ->addFilter(
                    SubscriptionInterface::STATUS,
                    self::RENEWABLE_SUBSCRIPTION_STATUSES,
                    'in'
                )
                ->setPageSize(1)
                ->create();

            return $this->subscriptionUsage[$tokenId] =
                $this->subscriptionRepository->getList($criteria)->getTotalCount() > 0;
        } catch (\Throwable) {
            // Token management must remain available if the optional subscription lookup fails.
            return $this->subscriptionUsage[$tokenId] = false;
        }
    }

    public function getDescription(PaymentTokenInterface $token): Phrase
    {
        if ($this->isUsedByRenewableSubscription($token)) {
            return __('EBizCharge saved card — used by a subscription');
        }
        return __('EBizCharge saved card');
    }

    public function getDeleteConfirmationMessage(PaymentTokenInterface $token): Phrase
    {
        $lastFour = $this->getNumberLast4Digits();
        if ($this->isUsedByRenewableSubscription($token)) {
            return __(
                'Are you sure you want to delete the card ending %1? '
                . 'This card is used by a subscription. Select another saved card for that subscription '
                . 'before its next renewal.',
                $lastFour
            );
        }
        return __('Are you sure you want to delete the card ending %1?', $lastFour);
    }

    /** @return array{url:mixed,width:mixed,height:mixed} */
    private function cardIcon(): array
    {
        return $this->getIconForType((string) ($this->getTokenDetails()['type'] ?? ''));
    }
}
