<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

/** Adds the configured EBizCharge command to a request. */
class CommandBuilder implements BuilderInterface
{
    public function __construct(
        private readonly string $command,
        private readonly string $soapMethod = 'runTransaction'
    ) {
    }

    public function build(array $buildSubject): array
    {
        return [
            'tran' => [
                'Command' => $this->command,
                // EBizCharge requires these non-null booleans during SOAP serialization.
                'IsRecurring' => false,
                'IgnoreDuplicate' => false,
                'CustReceipt' => false,
            ],
            '__method' => $this->soapMethod,
        ];
    }
}
