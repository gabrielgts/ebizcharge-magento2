<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Api\Data\SubscriptionSearchResultsInterface;
use Gtstudio\Ebizcharge\Api\Data\SubscriptionSearchResultsInterfaceFactory;
use Gtstudio\Ebizcharge\Api\SubscriptionRepositoryInterface;
use Gtstudio\Ebizcharge\Model\ResourceModel\Subscription as SubscriptionResource;
use Gtstudio\Ebizcharge\Model\ResourceModel\Subscription\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class SubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function __construct(
        private readonly SubscriptionResource $resource,
        private readonly SubscriptionFactory $factory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SubscriptionSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    public function save(SubscriptionInterface $subscription): SubscriptionInterface
    {
        try {
            /** @var Subscription $subscription */
            $this->resource->save($subscription);
            return $subscription;
        } catch (\Throwable $e) {
            throw new CouldNotSaveException(__('Could not save subscription: %1', $e->getMessage()), $e);
        }
    }

    public function getById(int $entityId): SubscriptionInterface
    {
        $model = $this->factory->create();
        $this->resource->load($model, $entityId);
        if (!$model->getId()) {
            throw new NoSuchEntityException(__('Subscription with id %1 does not exist.', $entityId));
        }
        return $model;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): SubscriptionSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var SubscriptionSearchResultsInterface $results */
        $results = $this->searchResultsFactory->create();
        $results->setSearchCriteria($searchCriteria);
        $results->setItems($collection->getItems());
        $results->setTotalCount($collection->getSize());
        return $results;
    }

    public function delete(SubscriptionInterface $subscription): bool
    {
        try {
            /** @var Subscription $subscription */
            $this->resource->delete($subscription);
            return true;
        } catch (\Throwable $e) {
            throw new CouldNotDeleteException(__('Could not delete subscription: %1', $e->getMessage()), $e);
        }
    }

    public function deleteById(int $entityId): bool
    {
        return $this->delete($this->getById($entityId));
    }
}
