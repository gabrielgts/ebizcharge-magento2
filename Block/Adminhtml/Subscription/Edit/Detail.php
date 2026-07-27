<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Block\Adminhtml\Subscription\Edit;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionChargeInterface;
use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Api\SubscriptionChargeRepositoryInterface;
use Gtstudio\Ebizcharge\Controller\Adminhtml\Subscription\Edit as EditController;
use Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionItem\Collection as ItemCollection;
use Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionItem\CollectionFactory as ItemCollectionFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Registry;
use Magento\Sales\Api\OrderRepositoryInterface;

/** Provides Admin subscription details and charge history. */
class Detail extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly SubscriptionChargeRepositoryInterface $chargeRepository,
        private readonly ItemCollectionFactory $itemCollectionFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getSubscription(): ?SubscriptionInterface
    {
        $entity = $this->registry->registry(EditController::REGISTRY_KEY);
        return $entity instanceof SubscriptionInterface ? $entity : null;
    }

    /** @return SubscriptionChargeInterface[] */
    public function getCharges(int $limit = 50): array
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
            ->setPageSize($limit)
            ->create();
        return $this->chargeRepository->getList($criteria)->getItems();
    }

    public function getItems(): array
    {
        $subscription = $this->getSubscription();
        if ($subscription === null) {
            return [];
        }
        /** @var ItemCollection $collection */
        $collection = $this->itemCollectionFactory->create();
        $collection->addFieldToFilter('subscription_id', (int) $subscription->getEntityId());
        return $collection->getItems();
    }

    public function getCustomerEmail(): string
    {
        $subscription = $this->getSubscription();
        if ($subscription === null) {
            return '';
        }
        try {
            $customer = $this->customerRepository->getById($subscription->getCustomerId());
            return (string) $customer->getEmail();
        } catch (\Throwable) {
            return '';
        }
    }

    public function getCustomerEditUrl(): string
    {
        $subscription = $this->getSubscription();
        if ($subscription === null) {
            return '';
        }
        return $this->getUrl('customer/index/edit', ['id' => $subscription->getCustomerId()]);
    }

    public function getOrderViewUrl(?int $orderId): ?string
    {
        if ($orderId === null) {
            return null;
        }
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Throwable) {
            return null;
        }
        return $this->getUrl('sales/order/view', ['order_id' => $order->getEntityId()]);
    }

    public function getOrderIncrement(?int $orderId): ?string
    {
        if ($orderId === null) {
            return null;
        }
        try {
            return (string) $this->orderRepository->get($orderId)->getIncrementId();
        } catch (\Throwable) {
            return null;
        }
    }

    public function getActionUrl(string $action): string
    {
        $subscription = $this->getSubscription();
        if ($subscription === null) {
            return '#';
        }
        return $this->getUrl(
            'gtstudio_ebizcharge/subscription/' . $action,
            ['id' => $subscription->getEntityId()]
        );
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('gtstudio_ebizcharge/subscription/index');
    }
}
