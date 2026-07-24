<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Http;

use Gtstudio\Ebizcharge\Gateway\Http\Client\SoapClient;

/** Internal adapter for calling non-transaction EBizCharge SOAP methods through the same client. */
class SoapMethodClient
{
    public function __construct(
        private readonly SoapClient $soapClient,
        private readonly TransferFactory $transferFactory
    ) {
    }

    /** @param array<string,mixed> $arguments @param array<string,mixed> $headers */
    public function call(string $method, array $arguments, array $headers = []): array
    {
        $arguments['__method'] = $method;
        if ($headers !== []) {
            $arguments['__headers'] = $headers;
        }

        return $this->soapClient->placeRequest($this->transferFactory->create($arguments));
    }
}
