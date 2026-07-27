<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Service;

use Gtstudio\Ebizcharge\Service\AchRoutingValidator;
use PHPUnit\Framework\TestCase;

class AchRoutingValidatorTest extends TestCase
{
    private AchRoutingValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new AchRoutingValidator();
    }

    /** Returns valid public routing-number fixtures. */
    public static function validRoutingProvider(): array
    {
        return [
            'Bank of America (NYC)' => ['021000322'],
            'Chase (NY)' => ['021000021'],
            'Wells Fargo' => ['121000248'],
            'JPMorgan' => ['021000021'],
            'Federal Reserve test' => ['011000015'],
        ];
    }

    /** @dataProvider validRoutingProvider */
    public function testValidRoutingNumbersPass(string $routing): void
    {
        $this->assertTrue($this->validator->isValid($routing));
    }

    public function testRoutingWithDashesIsAccepted(): void
    {
        $this->assertTrue($this->validator->isValid('021-000-322'));
    }

    public function testInvalidChecksumFails(): void
    {
        $this->assertFalse($this->validator->isValid('123456789'));
    }

    public function testTooShortFails(): void
    {
        $this->assertFalse($this->validator->isValid('12345678'));
    }

    public function testTooLongFails(): void
    {
        $this->assertFalse($this->validator->isValid('0210003220'));
    }

    public function testEmptyFails(): void
    {
        $this->assertFalse($this->validator->isValid(''));
    }

    public function testAllZeroesFails(): void
    {
        // Sum is zero — divisible by 10, but the validator explicitly rejects sum-of-zero
        $this->assertFalse($this->validator->isValid('000000000'));
    }

    public function testNonNumericIsCleanedThenValidated(): void
    {
        $this->assertTrue($this->validator->isValid('aaa021000322zzz'));
    }
}
