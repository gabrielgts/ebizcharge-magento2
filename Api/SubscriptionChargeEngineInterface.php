<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Api;

/**
 * Charges one pending subscription_charge row. Returns true on success, false on failure.
 *
 * Public extension point — wrap with plugins for fraud, throttling, alternative gateways, etc.
 *
 * @api
 */
interface SubscriptionChargeEngineInterface
{
    public function chargeOne(int $chargeId): bool;
}
