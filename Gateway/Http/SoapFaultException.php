<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Http;

use Magento\Payment\Gateway\Http\ClientException;
use SoapFault;

/**
 * Preserves machine-readable SOAP fault context for internal recovery decisions while exposing
 * only Magento's generic gateway message to checkout.
 */
class SoapFaultException extends ClientException
{
    public function __construct(
        private readonly string $faultCode,
        private readonly string $faultString,
        SoapFault $cause
    ) {
        parent::__construct(__('Gateway communication error.'), $cause);
    }

    public function isNotFound(): bool
    {
        return $this->containsAny(['notfound', 'not found']);
    }

    public function isDuplicate(): bool
    {
        return $this->containsAny(['already exists', 'record exists', 'duplicate']);
    }

    private function containsAny(array $needles): bool
    {
        $haystack = strtolower($this->faultCode . ' ' . $this->faultString);
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }
}
