<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Gateway\Http\Client;

use Gtstudio\Ebizcharge\Logger\Logger;
use Gtstudio\Ebizcharge\Service\CardProfileProvisioner;
use Gtstudio\Ebizcharge\Service\CorrelationIdProvider;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

/** Adds optional post-approval card-profile provisioning to initial card transactions. */
class VaultingClient implements ClientInterface
{
    public function __construct(
        private readonly SoapClient $transactionClient,
        private readonly CardProfileProvisioner $profileProvisioner,
        private readonly Logger $logger,
        private readonly CorrelationIdProvider $correlationId
    ) {
    }

    public function placeRequest(TransferInterface $transfer): array
    {
        $request = $transfer->getBody();
        $metadata = $request['__vault_profile'] ?? null;
        $response = $this->transactionClient->placeRequest($transfer);

        if (!is_array($metadata) || (string) ($response['ResultCode'] ?? '') !== 'A') {
            return $response;
        }

        try {
            $identifiers = $this->profileProvisioner->provision($request, $metadata, $transfer->getHeaders());
            $response['CustNum'] = $identifiers['cust_num'];
            $response['PaymentMethodID'] = $identifiers['method_id'];
            $response['VaultSaveStatus'] = 'saved';
        } catch (\Throwable $e) {
            // Vault saving is optional. The already-approved payment remains authoritative.
            $response['VaultSaveStatus'] = 'failed';
            $this->logger->warning('vault.profile_provision_failed', [
                'correlation_id' => $this->correlationId->get(),
                'reason' => $e::class,
            ]);
        }

        return $response;
    }
}
