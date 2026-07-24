<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\ViewModel\Customer;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionChargeInterface;
use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionChargeRepositoryInterface;
use Gtstudio\Ebizcharge\Controller\Subscription\View as ViewController;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Powers the storefront subscription detail page (charge history + items + summary).
 *
 * Reads the current subscription from registry (set by ViewController). Returns null if absent —
 * the template handles the "not found" case rather than throwing.
 */
class SubscriptionView implements ArgumentInterface
{
    public function __construct(
        private readonly Registry $registry,
        private readonly SubscriptionChargeRepositoryInterface $chargeRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder
    ) {
    }

    public function getSubscription(): ?SubscriptionInterface
    {
        $entity = $this->registry->registry(ViewController::REGISTRY_KEY);
        return $entity instanceof SubscriptionInterface ? $entity : null;
    }

    /** @return SubscriptionChargeInterface[] */
    public function getCharges(): array
    {
        $subscription = $this->getSubscription();
        if ($subscription === null) {
            return [];
        }
        $criteria = $this->searchCriteriaBuilder
            ->addFilter(SubscriptionChargeInterface::SUBSCRIPTION_ID, (int) $subscription->getEntityId())
            ->addSortOrder(
                $this->sortOrderBuilder
                    ->setField(SubscriptionChargeInterface::SCHEDULED_FOR)
                    ->setDirection('DESC')
                    ->create()
            )
            ->create();
        return $this->chargeRepository->getList($criteria)->getItems();
    }
}
