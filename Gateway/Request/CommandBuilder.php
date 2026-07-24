<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Sets the EBizCharge command (sale, authonly, capture, refund, creditvoid, check) on the request body.
 *
 * Wired via virtualType in di.xml — one instance per command name.
 */
class CommandBuilder implements BuilderInterface
{
    public function __construct(private readonly string $command, private readonly string $soapMethod = 'runTransaction')
    {
    }

    public function build(array $buildSubject): array
    {
        return [
            'tran' => [
                'Command' => $this->command,
                // TransactionRequestObject declares these three as minOccurs="1" non-nillable
                // booleans. PHP's SOAP encoder refuses to serialize the whole request if any is
                // absent ("SOAP-ERROR: Encoding: object has no 'IsRecurring' property"), so they
                // must be on every command — this builder is the one present in all of them.
                // Same values the legacy module sent (TranApi::getTransactionRequest).
                'IsRecurring' => false,
                'IgnoreDuplicate' => false,
                'CustReceipt' => false,
            ],
            '__method' => $this->soapMethod,
        ];
    }
}
