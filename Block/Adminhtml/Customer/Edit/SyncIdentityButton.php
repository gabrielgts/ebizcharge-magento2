<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Block\Adminhtml\Customer\Edit;

use Magento\Customer\Block\Adminhtml\Edit\GenericButton;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class SyncIdentityButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        $customerId = (int) ($this->getCustomerId() ?? 0);
        if ($customerId <= 0) {
            return [];
        }

        return [
            'label' => __('Verify / Sync EBizCharge'),
            'class' => 'secondary',
            'data_attribute' => [
                'mage-init' => [
                    'Gtstudio_Ebizcharge/js/customer-identity-sync' => [
                        'url' => $this->getUrl('gtstudio_ebizcharge/customeridentity/sync'),
                        'customerId' => $customerId,
                        'confirmation' => __(
                            'Verify this customer in EBizCharge and create it there if it does not exist?'
                        ),
                    ],
                ],
            ],
            'sort_order' => 85,
        ];
    }
}
