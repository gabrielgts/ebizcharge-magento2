<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

/**
 * Request-local handoff for raw payment data that must never be persisted on a quote or order.
 *
 * Magento services are shared within one PHP request. Data is keyed by quote ID and consumed once
 * by the gateway request builder, preventing PAN/CVV from entering additional_information while
 * still making it available when the order payment command is built later in the same request.
 */
final class SensitivePaymentData
{
    /** @var array<int,array{cc_number:string,cc_cid:string}> */
    private array $cardData = [];

    public function storeCardData(int $quoteId, string $pan, string $cvv): void
    {
        if ($quoteId <= 0) {
            return;
        }

        $this->cardData[$quoteId] = [
            'cc_number' => $pan,
            'cc_cid' => $cvv,
        ];
    }

    /** @return array{cc_number:string,cc_cid:string} */
    public function consumeCardData(int $quoteId): array
    {
        $data = $this->cardData[$quoteId] ?? ['cc_number' => '', 'cc_cid' => ''];
        unset($this->cardData[$quoteId]);

        return $data;
    }
}
