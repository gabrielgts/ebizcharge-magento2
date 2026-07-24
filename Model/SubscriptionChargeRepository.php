<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionChargeInterface;
use Gtstudio\Ebizcharge\Api\Data\SubscriptionChargeSearchResultsInterface;
use Gtstudio\Ebizcharge\Api\Data\SubscriptionChargeSearchResultsInterfaceFactory;
use Gtstudio\Ebizcharge\Api\SubscriptionChargeRepositoryInterface;
use Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionCharge as SubscriptionChargeResource;
use Gtstudio\Ebizcharge\Model\ResourceModel\SubscriptionCharge\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class SubscriptionChargeRepository implements SubscriptionChargeRepositoryInterface
{
    public function __construct(
        private readonly SubscriptionChargeResource $resource,
        private readonly SubscriptionChargeFactory $factory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SubscriptionChargeSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    public function save(SubscriptionChargeInterface $charge): SubscriptionChargeInterface
    {
        try {
            /** @var SubscriptionCharge $charge */
            $this->resource->save($charge);
            return $charge;
        } catch (\Throwable $e) {
            throw new CouldNotSaveException(__('Could not save subscription charge: %1', $e->getMessage()), $e);
        }
    }

    public function getById(int $entityId): SubscriptionChargeInterface
    {
        $model = $this->factory->create();
        $this->resource->load($model, $entityId);
        if (!$model->getId()) {
            throw new NoSuchEntityException(__('Subscription charge with id %1 does not exist.', $entityId));
        }
        return $model;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): SubscriptionChargeSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var SubscriptionChargeSearchResultsInterface $results */
        $results = $this->searchResultsFactory->create();
        $results->setSearchCriteria($searchCriteria);
        $results->setItems($collection->getItems());
        $results->setTotalCount($collection->getSize());
        return $results;
    }
}
