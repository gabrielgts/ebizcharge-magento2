<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model\Adminhtml\Source;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Framework\Data\OptionSourceInterface;

class SubscriptionFrequency extends AbstractSource implements OptionSourceInterface
{
    public function getAllOptions(): array
    {
        if ($this->_options === null) {
            $this->_options = [
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

        return $this->_options;
    }

    /** @return array<string,string> */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->getAllOptions() as $row) {
            $result[$row['value']] = (string) $row['label'];
        }
        return $result;
    }
}
