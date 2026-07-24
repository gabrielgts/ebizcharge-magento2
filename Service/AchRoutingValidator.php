<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

/**
 * Validates a 9-digit US bank routing number using the ABA checksum.
 *
 * Formula: 3*d1 + 7*d2 + 1*d3 + 3*d4 + 7*d5 + 1*d6 + 3*d7 + 7*d8 + 1*d9 must be divisible by 10.
 * Used by DataAssignObserver to reject malformed input at checkout submission rather than at
 * request-build time (cleaner error path to the user).
 */
class AchRoutingValidator
{
    private const WEIGHTS = [3, 7, 1, 3, 7, 1, 3, 7, 1];

    public function isValid(string $routing): bool
    {
        $digits = preg_replace('/\D/', '', $routing) ?? '';
        if (strlen($digits) !== 9) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $digits[$i]) * self::WEIGHTS[$i];
        }
        return $sum > 0 && $sum % 10 === 0;
    }
}
