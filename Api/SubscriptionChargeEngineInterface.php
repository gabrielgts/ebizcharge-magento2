<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Api;

/** @api */
interface SubscriptionChargeEngineInterface
{
    public function chargeOne(int $chargeId): bool;
}
