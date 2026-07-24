<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Config;

use PHPUnit\Framework\TestCase;

class VaultWiringTest extends TestCase
{
    public function testDedicatedVaultConfigAndMagentoTokenProviderAreRegistered(): void
    {
        $xml = $this->di();

        $this->assertSame(
            'Gtstudio\Ebizcharge\Gateway\Config\Config\VaultMethod',
            $this->argument($xml, 'virtualType', 'GtstudioEbizchargeVaultFacade', 'config')
        );
        $this->assertSame(
            'GtstudioEbizchargeVaultValueHandlerPool',
            $this->argument($xml, 'virtualType', 'GtstudioEbizchargeVaultFacade', 'valueHandlerPool')
        );
        $provider = $xml->xpath(
            '//type[@name="Magento\Vault\Model\Ui\TokensConfigProvider"]'
            . '/arguments/argument[@name="tokenUiComponentProviders"]'
            . '/item[@name="gtstudio_ebizcharge"]'
        );
        $this->assertNotEmpty($provider);
        $this->assertSame(
            'Gtstudio\Ebizcharge\Model\Vault\TokenUiComponentProvider',
            trim((string) $provider[0])
        );
    }

    public function testInitialCardCommandsUsePostApprovalProvisioningClient(): void
    {
        $xml = $this->di();
        foreach (['GtstudioEbizchargeAuthorizeCommand', 'GtstudioEbizchargeSaleCommand'] as $command) {
            $this->assertSame(
                'Gtstudio\Ebizcharge\Gateway\Http\Client\VaultingClient',
                $this->argument($xml, 'virtualType', $command, 'client')
            );
        }
        foreach (['GtstudioEbizchargeAuthorizeRequest', 'GtstudioEbizchargeSaleRequest'] as $request) {
            $builder = $xml->xpath(
                '//virtualType[@name="' . $request . '"]'
                . '/arguments/argument[@name="builders"]'
                . '/item[@name="vault_profile"]'
            );
            $this->assertNotEmpty($builder);
            $this->assertSame(
                'Gtstudio\Ebizcharge\Gateway\Request\VaultProfileDataBuilder',
                trim((string) $builder[0])
            );
        }
    }

    public function testSavedCardCommandsUseRunCustomerTransactionBuilder(): void
    {
        $xml = $this->di();
        foreach (['GtstudioEbizchargeVaultAuthorizeRequest', 'GtstudioEbizchargeVaultSaleRequest'] as $request) {
            $builder = $xml->xpath(
                '//virtualType[@name="' . $request . '"]'
                . '/arguments/argument[@name="builders"]'
                . '/item[@name="vault_data"]'
            );
            $this->assertNotEmpty($builder);
            $this->assertSame(
                'Gtstudio\Ebizcharge\Gateway\Request\VaultDataBuilder',
                trim((string) $builder[0])
            );
        }
    }

    public function testCheckoutUsesMagentoVaultEnablerAndNoPrivateCardSaveContract(): void
    {
        $module = dirname(__DIR__, 3);
        $js = (string) file_get_contents(
            $module . '/view/frontend/web/js/view/payment/method-renderer/gtstudio-ebizcharge.js'
        );
        $template = (string) file_get_contents(
            $module . '/view/frontend/web/template/payment/gtstudio-ebizcharge.html'
        );
        $observer = (string) file_get_contents($module . '/Observer/DataAssignObserver.php');

        $this->assertStringContainsString('Magento_Vault/js/view/payment/vault-enabler', $js);
        $this->assertStringContainsString('visitAdditionalData(data)', $js);
        $this->assertStringContainsString('vault[is_enabled]', $template);
        $this->assertStringContainsString('vaultEnabler.isActivePaymentTokenEnabler', $template);
        $this->assertStringNotContainsString('cc_save', $js . $template . $observer);
    }

    public function testCustomerRendererAndBestEffortRepositoryDeleteHookAreRegistered(): void
    {
        $module = dirname(__DIR__, 3);
        $layout = simplexml_load_file($module . '/view/frontend/layout/vault_cards_listaction.xml');
        $this->assertInstanceOf(\SimpleXMLElement::class, $layout);
        $blocks = $layout->xpath('//block[@class="Gtstudio\Ebizcharge\Block\Customer\CardRenderer"]');
        $this->assertNotEmpty($blocks);

        $plugins = $this->di()->xpath(
            '//type[@name="Magento\Vault\Model\PaymentTokenRepository"]'
            . '/plugin[@type="Gtstudio\Ebizcharge\Plugin\Vault\DeleteStoredCardPlugin"]'
        );
        $this->assertNotEmpty($plugins);
    }

    private function di(): \SimpleXMLElement
    {
        $xml = simplexml_load_file(dirname(__DIR__, 3) . '/etc/di.xml');
        $this->assertInstanceOf(\SimpleXMLElement::class, $xml);
        return $xml;
    }

    private function argument(
        \SimpleXMLElement $xml,
        string $node,
        string $objectName,
        string $argumentName
    ): string {
        $result = $xml->xpath(
            '//' . $node . '[@name="' . $objectName . '"]'
            . '/arguments/argument[@name="' . $argumentName . '"]'
        );
        $this->assertNotEmpty($result);
        return trim((string) $result[0]);
    }
}
