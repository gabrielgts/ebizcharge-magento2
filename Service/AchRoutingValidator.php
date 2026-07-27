<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Service;

/** Validates US bank routing numbers with the ABA checksum. */
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
