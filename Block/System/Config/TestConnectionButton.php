<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders the "Test Connection" button on the Credentials admin config group.
 *
 * Displays an inline result panel; the button posts the currently-entered (un-saved) credentials
 * to the AJAX controller via JS, so admins can verify before clicking Save.
 */
class TestConnectionButton extends Field
{
    /** @var string */
    protected $_template = 'Gtstudio_Ebizcharge::system/config/test_connection.phtml';

    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        // Derive sibling ids from this element's own id rather than hardcoding them: the id is
        // built from the system.xml path by Magento and has changed across versions.
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

    /**
     * Required override so the field row renders without a value column.
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }
}
