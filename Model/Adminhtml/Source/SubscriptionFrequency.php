<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model\Adminhtml\Source;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Magento\Framework\Data\OptionSourceInterface;

class SubscriptionFrequency implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => SubscriptionInterface::FREQUENCY_DAILY, 'label' => __('Daily')],
            ['value' => SubscriptionInterface::FREQUENCY_WEEKLY, 'label' => __('Weekly')],
            ['value' => SubscriptionInterface::FREQUENCY_BIWEEKLY, 'label' => __('Bi-weekly')],
            ['value' => SubscriptionInterface::FREQUENCY_MONTHLY, 'label' => __('Monthly')],
            ['value' => SubscriptionInterface::FREQUENCY_BIMONTHLY, 'label' => __('Bi-monthly')],
            ['value' => SubscriptionInterface::FREQUENCY_QUARTERLY, 'label' => __('Quarterly')],
            ['value' => SubscriptionInterface::FREQUENCY_BIANNUALLY, 'label' => __('Bi-annually')],
            ['value' => SubscriptionInterface::FREQUENCY_ANNUALLY, 'label' => __('Annually')],
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
