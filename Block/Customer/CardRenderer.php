<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Block\Customer;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractCardRenderer;

class CardRenderer extends AbstractCardRenderer
{
    public function canRender(PaymentTokenInterface $token): bool
    {
        return $token->getPaymentMethodCode() === Config::METHOD_CODE;
    }

    public function getNumberLast4Digits(): string
    {
        return (string) ($this->getTokenDetails()['maskedCC'] ?? '');
    }

    public function getExpDate(): string
    {
        return (string) ($this->getTokenDetails()['expirationDate'] ?? '');
    }

    public function getIconUrl(): string
    {
        return (string) $this->cardIcon()['url'];
    }

    public function getIconHeight(): int
    {
        return (int) $this->cardIcon()['height'];
    }

    public function getIconWidth(): int
    {
        return (int) $this->cardIcon()['width'];
    }

    /** @return array{url:mixed,width:mixed,height:mixed} */
    private function cardIcon(): array
    {
        return $this->getIconForType((string) ($this->getTokenDetails()['type'] ?? ''));
    }
}
