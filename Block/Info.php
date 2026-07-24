<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Block;

use Magento\Payment\Block\ConfigurableInfo;

class Info extends ConfigurableInfo
{
    /**
     * @var string
     */
    protected $_template = 'Gtstudio_Ebizcharge::info/info.phtml';

    protected function getLabel($field): \Magento\Framework\Phrase
    {
        return match ($field) {
            'cc_last_4' => __('Card ending in'),
            'cc_type' => __('Card Type'),
            'cc_exp_month' => __('Expiration Month'),
            'cc_exp_year' => __('Expiration Year'),
            'gtstudio_correlation_id' => __('Correlation ID'),
            'gtstudio_result_code' => __('Result'),
            'gtstudio_batch_num' => __('Batch'),
            default => __(ucwords(str_replace('_', ' ', (string) $field))),
        };
    }
}
