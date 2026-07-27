<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

/** Holds sensitive payment data for one request. */
class SensitivePaymentData
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
