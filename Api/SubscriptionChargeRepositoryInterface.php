<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Api;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionChargeInterface;
use Gtstudio\Ebizcharge\Api\Data\SubscriptionChargeSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/** @api */
interface SubscriptionChargeRepositoryInterface
{
    /** @throws CouldNotSaveException */
    public function save(SubscriptionChargeInterface $charge): SubscriptionChargeInterface;

    /** @throws NoSuchEntityException */
    public function getById(int $entityId): SubscriptionChargeInterface;

    public function getList(SearchCriteriaInterface $searchCriteria): SubscriptionChargeSearchResultsInterface;
}
