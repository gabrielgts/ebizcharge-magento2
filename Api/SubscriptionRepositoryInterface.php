<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Api;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Api\Data\SubscriptionSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @api
 */
interface SubscriptionRepositoryInterface
{
    /** @throws CouldNotSaveException */
    public function save(SubscriptionInterface $subscription): SubscriptionInterface;

    /** @throws NoSuchEntityException */
    public function getById(int $entityId): SubscriptionInterface;

    public function getList(SearchCriteriaInterface $searchCriteria): SubscriptionSearchResultsInterface;

    /** @throws CouldNotDeleteException */
    public function delete(SubscriptionInterface $subscription): bool;

    /** @throws NoSuchEntityException|CouldNotDeleteException */
    public function deleteById(int $entityId): bool;
}
