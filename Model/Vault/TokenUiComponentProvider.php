<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model\Vault;

use Magento\Framework\View\Element\Template;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;

/**
 * Renders one saved card in the checkout's saved-cards list. The frontend KO component
 * (gtstudio-ebizcharge-vault-method.js) reads the details to display brand + last4 + expiration.
 */
class TokenUiComponentProvider implements TokenUiComponentProviderInterface
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
                'code' => 'gtstudio_ebizcharge_cc_vault',
                'nameOnCard' => '',
                TokenUiComponentProviderInterface::COMPONENT_DETAILS => $details,
                TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash(),
                'template' => 'Gtstudio_Ebizcharge/payment/vault',
            ],
            'name' => 'Gtstudio_Ebizcharge/js/view/payment/method-renderer/vault',
        ]);
    }
}
