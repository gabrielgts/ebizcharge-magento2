<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/** Renders the Admin gateway connection-test control. */
class TestConnectionButton extends Field
{
    /** @var string */
    protected $_template = 'Gtstudio_Ebizcharge::system/config/test_connection.phtml';

    protected function _getElementHtml(AbstractElement $element): string
    {
        // Derive sibling IDs from Magento's generated system-config element ID.
        $htmlId = $element->getHtmlId();
        $prefix = substr($htmlId, 0, -strlen('test_connection'));

        $this->addData([
            'button_label' => __('Test Connection'),
            'html_id' => $htmlId,
            'ajax_url' => $this->getUrl('gtstudio_ebizcharge/system_config/testConnection'),
            'user_id_field' => $prefix . 'user_id',
            'security_id_field' => $prefix . 'security_id',
            'password_field' => $prefix . 'password',
            'endpoint_mode_field' => $prefix . 'endpoint_mode',
            'endpoint_override_field' => $prefix . 'endpoint_url_override',
        ]);
        return $this->_toHtml();
    }

    /** Renders the field row without a value column. */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }
}
