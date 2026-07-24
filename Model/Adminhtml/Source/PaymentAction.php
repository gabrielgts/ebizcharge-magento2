<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model\Adminhtml\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Payment\Model\Method\AbstractMethod;

class PaymentAction implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => AbstractMethod::ACTION_AUTHORIZE, 'label' => __('Authorize Only')],
            ['value' => AbstractMethod::ACTION_AUTHORIZE_CAPTURE, 'label' => __('Authorize and Capture')],
        ];
    }
}
