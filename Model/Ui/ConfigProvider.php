<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Model\Ui;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Model\CcConfig;
use Magento\Store\Model\StoreManagerInterface;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'gtstudio_ebizcharge';
    public const CODE_ACH = 'gtstudio_ebizcharge_ach';

    public function __construct(
        private readonly Config $config,
        private readonly Config $achConfig,
        private readonly CcConfig $ccConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getConfig(): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $payment = [];

        if ($this->config->isActive($storeId)) {
            // cvvImageUrl/months/years/availableTypes come from CcGenericConfigProvider under
            // the shared `ccform` key (see etc/frontend/di.xml) — cc-form.js reads them there.
            $payment[self::CODE] = [
                'isSandbox' => $this->config->isSandbox($storeId),
                'availableCardTypes' => $this->getCcAvailableTypes($storeId),
                'ccVaultCode' => Config::METHOD_CODE_VAULT,
            ];
        }
        if ($this->achConfig->isActive($storeId)) {
            $payment[self::CODE_ACH] = [
                'isSandbox' => $this->achConfig->isSandbox($storeId),
            ];
        }

        return $payment === [] ? [] : ['payment' => $payment];
    }

    /** @return array<string,string> */
    private function getCcAvailableTypes(int $storeId): array
    {
        $allowed = $this->config->getAllowedCcTypes($storeId);
        $all = $this->ccConfig->getCcAvailableTypes();
        $result = [];
        foreach ($allowed as $code) {
            if (isset($all[$code])) {
                $result[$code] = $all[$code];
            }
        }
        return $result;
    }
}
