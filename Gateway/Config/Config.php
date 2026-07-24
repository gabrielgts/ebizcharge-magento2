<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Config;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Payment\Gateway\Config\Config as PaymentConfig;

/**
 * Typed accessor over scopeConfig for the gtstudio_ebizcharge payment method.
 *
 * Credentials are stored encrypted (system.xml -> backend_model="Magento\Config\Model\Config\Backend\Encrypted")
 * and decrypted on read here.
 */
class Config extends PaymentConfig
{
    public const METHOD_CODE = 'gtstudio_ebizcharge';
    public const METHOD_CODE_ACH = 'gtstudio_ebizcharge_ach';
    public const METHOD_CODE_VAULT = 'gtstudio_ebizcharge_cc_vault';
    public const METHOD_CODE_ACH_VAULT = 'gtstudio_ebizcharge_ach_vault';

    public const KEY_ACTIVE = 'active';
    public const KEY_TITLE = 'title';
    public const KEY_PAYMENT_ACTION = 'payment_action';
    public const KEY_USER_ID = 'user_id';
    public const KEY_SECURITY_ID = 'security_id';
    public const KEY_PASSWORD = 'password';
    public const KEY_ENDPOINT_MODE = 'endpoint_mode';
    public const KEY_ENDPOINT_URL_OVERRIDE = 'endpoint_url_override';
    public const KEY_CC_TYPES = 'cctypes';
    public const KEY_DEBUG = 'debug';
    public const KEY_DESCRIPTION = 'description';
    public const KEY_SOFTWARE_TAG = 'software_tag';
    public const KEY_SOAP_TIMEOUT = 'soap_timeout';
    public const KEY_SOAP_CONNECT_TIMEOUT = 'soap_connect_timeout';
    public const KEY_SUBSCRIPTION_FAILURE_THRESHOLD_FAILING = 'subscription_failure_threshold_failing';
    public const KEY_SUBSCRIPTION_FAILURE_THRESHOLD_CANCEL = 'subscription_failure_threshold_cancel';

    public const ENDPOINT_PRODUCTION = 'production';
    public const ENDPOINT_SANDBOX = 'sandbox';

    public const URL_PRODUCTION = 'https://soap.ebizcharge.net/eBizService.svc?singleWsdl';
    // Intentionally identical to production. EBizCharge does not segregate sandbox by URL — it
    // dropped the sandbox endpoint from the integration in 2.3.2 ("Sandbox removed from admin
    // settings") and the legacy module has hardcoded this single host ever since. Whether traffic
    // is sandbox or live is decided by the merchant account behind the credentials, so
    // endpoint_mode only drives the storefront test-mode banner. Endpoint URL Override remains
    // for one-off targets (e.g. an EBizCharge-supplied dev host).
    public const URL_SANDBOX = 'https://soap.ebizcharge.net/eBizService.svc?singleWsdl';

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        string $methodCode = self::METHOD_CODE,
        string $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
    }

    public function isActive(?int $storeId = null): bool
    {
        return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
    }

    public function getTitle(?int $storeId = null): string
    {
        return (string) $this->getValue(self::KEY_TITLE, $storeId);
    }

    public function getPaymentAction(?int $storeId = null): string
    {
        $action = (string) $this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
        return $action !== '' ? $action : 'authorize';
    }

    public function getUserId(?int $storeId = null): string
    {
        return $this->decrypt((string) $this->getValue(self::KEY_USER_ID, $storeId));
    }

    public function getSecurityId(?int $storeId = null): string
    {
        return $this->decrypt((string) $this->getValue(self::KEY_SECURITY_ID, $storeId));
    }

    public function getPassword(?int $storeId = null): string
    {
        return $this->decrypt((string) $this->getValue(self::KEY_PASSWORD, $storeId));
    }

    public function isSandbox(?int $storeId = null): bool
    {
        return (string) $this->getValue(self::KEY_ENDPOINT_MODE, $storeId) === self::ENDPOINT_SANDBOX;
    }

    public function getEndpointUrl(?int $storeId = null): string
    {
        $override = trim((string) $this->getValue(self::KEY_ENDPOINT_URL_OVERRIDE, $storeId));
        if ($override !== '') {
            return $override;
        }
        return $this->isSandbox($storeId) ? self::URL_SANDBOX : self::URL_PRODUCTION;
    }

    /** @return string[] */
    public function getAllowedCcTypes(?int $storeId = null): array
    {
        $raw = (string) $this->getValue(self::KEY_CC_TYPES, $storeId);
        return $raw === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function isDebugEnabled(?int $storeId = null): bool
    {
        return (bool) $this->getValue(self::KEY_DEBUG, $storeId);
    }

    public function getDescription(?int $storeId = null): string
    {
        $value = (string) $this->getValue(self::KEY_DESCRIPTION, $storeId);
        return $value !== '' ? $value : 'Magento Order [orderid]';
    }

    public function getSoftwareTag(?int $storeId = null): string
    {
        $value = (string) $this->getValue(self::KEY_SOFTWARE_TAG, $storeId);
        return $value !== '' ? $value : 'Magento2-Gtstudio';
    }

    public function getSoapReadTimeout(?int $storeId = null): int
    {
        $value = (int) $this->getValue(self::KEY_SOAP_TIMEOUT, $storeId);
        return $value > 0 ? $value : 60;
    }

    public function getSoapConnectTimeout(?int $storeId = null): int
    {
        $value = (int) $this->getValue(self::KEY_SOAP_CONNECT_TIMEOUT, $storeId);
        return $value > 0 ? $value : 30;
    }

    public function getSubscriptionFailureThresholdFailing(?int $storeId = null): int
    {
        $value = (int) $this->getValue(self::KEY_SUBSCRIPTION_FAILURE_THRESHOLD_FAILING, $storeId);
        return $value > 0 ? $value : 3;
    }

    public function getSubscriptionFailureThresholdCancel(?int $storeId = null): int
    {
        $failing = $this->getSubscriptionFailureThresholdFailing($storeId);
        $value = (int) $this->getValue(self::KEY_SUBSCRIPTION_FAILURE_THRESHOLD_CANCEL, $storeId);
        return $value > $failing ? $value : max(5, $failing + 1);
    }

    private function decrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }
        return (string) $this->encryptor->decrypt($value);
    }
}
