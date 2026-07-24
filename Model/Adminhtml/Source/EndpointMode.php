<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model\Adminhtml\Source;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Magento\Framework\Data\OptionSourceInterface;

class EndpointMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::ENDPOINT_PRODUCTION, 'label' => __('Production')],
            ['value' => Config::ENDPOINT_SANDBOX, 'label' => __('Sandbox')],
        ];
    }
}
