<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model\Adminhtml\Source;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Magento\Framework\Data\OptionSourceInterface;

class SubscriptionStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => SubscriptionInterface::STATUS_ACTIVE, 'label' => __('Active')],
            ['value' => SubscriptionInterface::STATUS_PAUSED, 'label' => __('Paused')],
            ['value' => SubscriptionInterface::STATUS_FAILING, 'label' => __('Failing')],
            ['value' => SubscriptionInterface::STATUS_CANCELLED, 'label' => __('Cancelled')],
            ['value' => SubscriptionInterface::STATUS_EXPIRED, 'label' => __('Expired')],
            ['value' => SubscriptionInterface::STATUS_COMPLETED, 'label' => __('Completed')],
        ];
    }

    /** @return array<string,string> */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->toOptionArray() as $row) {
            $result[$row['value']] = (string) $row['label'];
        }
        return $result;
    }
}
