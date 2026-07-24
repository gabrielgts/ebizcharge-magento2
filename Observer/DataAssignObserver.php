<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Observer;

use Gtstudio\Ebizcharge\Gateway\Request\AchDataBuilder;
use Gtstudio\Ebizcharge\Service\AchRoutingValidator;
use Gtstudio\Ebizcharge\Service\SensitivePaymentData;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Forwards non-sensitive checkout fields into payment additional_information. PAN/CVV are moved
 * into a request-local in-memory handoff so quote persistence can never contain raw card data.
 *
 * The Adapter payment method (unlike the deprecated Method\Cc) does not auto-populate these,
 * so this observer is required for the new module to function.
 *
 * Also runs request-time ABA validation on the routing number — fails fast at checkout submission
 * rather than at the gateway, so the user sees a clean error message.
 */
class DataAssignObserver extends AbstractDataAssignObserver
{
    /** @var string[] */
    public const ADDITIONAL_KEYS = [
        // Credit card
        'cc_exp_month',
        'cc_exp_year',
        'cc_type',
        'cc_owner',
        // ACH
        AchDataBuilder::KEY_ACCOUNT,
        AchDataBuilder::KEY_ROUTING,
        AchDataBuilder::KEY_ACCOUNT_TYPE,
        // Vault
        'public_hash',
    ];

    public function __construct(
        private readonly AchRoutingValidator $routingValidator,
        private readonly SensitivePaymentData $sensitivePaymentData
    ) {
    }

    public function execute(Observer $observer): void
    {
        $data = $this->readDataArgument($observer);
        $additional = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additional)) {
            return;
        }

        $this->validateAch($additional);

        $payment = $this->readPaymentModelArgument($observer);
        $this->moveCardDataToMemory($payment, $additional);
        foreach (self::ADDITIONAL_KEYS as $key) {
            if (isset($additional[$key]) && $additional[$key] !== '') {
                $payment->setAdditionalInformation($key, $additional[$key]);
            }
        }
    }

    /** @param array<string,mixed> $additional */
    private function moveCardDataToMemory(object $payment, array $additional): void
    {
        $pan = (string) ($additional['cc_number'] ?? '');
        $cvv = (string) ($additional['cc_cid'] ?? '');

        if ($pan !== '' || $cvv !== '') {
            $this->sensitivePaymentData->storeCardData((int) $payment->getQuoteId(), $pan, $cvv);
        }

        // Also remove values left by an earlier failed attempt before the quote is saved again.
        $payment->unsAdditionalInformation('cc_number');
        $payment->unsAdditionalInformation('cc_cid');
    }

    /**
     * @param array<string,mixed> $additional
     * @throws LocalizedException
     */
    private function validateAch(array $additional): void
    {
        $routing = trim((string) ($additional[AchDataBuilder::KEY_ROUTING] ?? ''));
        if ($routing === '') {
            return;
        }
        if (!$this->routingValidator->isValid($routing)) {
            throw new LocalizedException(__('The bank routing number is invalid.'));
        }
    }
}
