<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model\Vault;

use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;

/**
 * Renders one saved ACH bank account in the checkout's saved-methods list.
 * Symmetric to TokenUiComponentProvider (cards), but with bank-specific details.
 */
class AchTokenUiComponentProvider implements TokenUiComponentProviderInterface
{
    public function __construct(private readonly TokenUiComponentInterfaceFactory $componentFactory)
    {
    }

    public function getComponentForToken(PaymentTokenInterface $paymentToken): TokenUiComponentInterface
    {
        $jsonDetails = (string) $paymentToken->getTokenDetails();
        $details = $jsonDetails === '' ? [] : (array) json_decode($jsonDetails, true);

        return $this->componentFactory->create([
            'config' => [
                'code' => 'gtstudio_ebizcharge_ach_vault',
                TokenUiComponentProviderInterface::COMPONENT_DETAILS => $details,
                TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash(),
                'template' => 'Gtstudio_Ebizcharge/payment/ach-vault',
            ],
            'name' => 'Gtstudio_Ebizcharge/js/view/payment/method-renderer/ach-vault',
        ]);
    }
}
