<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Http;

use Magento\Payment\Gateway\Http\ConverterException;

/** Normalizes wrapped EBizCharge SOAP responses. */
class ResponseNormalizer
{
    public function normalize(mixed $response, string $soapMethod): array
    {
        $decoded = $this->decode($response);
        $resultKey = $soapMethod . 'Result';

        if (!array_key_exists($resultKey, $decoded) || !is_array($decoded[$resultKey])) {
            return $decoded;
        }

        return $decoded[$resultKey];
    }

    private function decode(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }
        if (is_object($response)) {
            $encoded = json_encode($response);
            if ($encoded === false) {
                throw new ConverterException(__('Failed to decode gateway response.'));
            }
            $decoded = json_decode($encoded, true);
            if (!is_array($decoded)) {
                throw new ConverterException(__('Failed to decode gateway response.'));
            }
            return $decoded;
        }

        return ['raw' => $response];
    }
}
