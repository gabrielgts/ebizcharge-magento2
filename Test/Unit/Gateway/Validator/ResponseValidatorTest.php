<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Gateway\Validator;

use Gtstudio\Ebizcharge\Gateway\Validator\ResponseValidator;
use Magento\Payment\Gateway\Validator\Result;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use PHPUnit\Framework\TestCase;

/**
 * Validates the routing of EBizCharge result codes to Magento approve/decline/error states.
 *
 * This is the gate that decides whether a charge "succeeded" — getting it wrong here means
 * orders that should have failed get marked paid (or vice-versa).
 */
class ResponseValidatorTest extends TestCase
{
    private ResponseValidator $validator;

    protected function setUp(): void
    {
        $resultFactory = $this->createMock(ResultInterfaceFactory::class);
        $resultFactory->method('create')->willReturnCallback(
            fn (array $args) => new Result((bool) $args['isValid'], (array) ($args['failsDescription'] ?? []), (array) ($args['errorCodes'] ?? []))
        );
        $this->validator = new ResponseValidator($resultFactory);
    }

    public function testApprovedResultCodeIsValid(): void
    {
        $result = $this->validator->validate(['response' => ['ResultCode' => 'A']]);
        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getFailsDescription());
    }

    public function testDeclinedResultCodeIsInvalidWithCustomerFacingMessage(): void
    {
        $result = $this->validator->validate(['response' => [
            'ResultCode' => 'D',
            'Error' => 'Card declined',
        ]]);
        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->getFailsDescription());
        $this->assertStringContainsString('Card declined', (string) $result->getFailsDescription()[0]);
    }

    public function testErrorResultCodeIsInvalid(): void
    {
        $result = $this->validator->validate(['response' => [
            'ResultCode' => 'E',
            'Error' => 'Gateway timeout',
        ]]);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Gateway timeout', (string) $result->getFailsDescription()[0]);
    }

    public function testMissingResultCodeIsTreatedAsError(): void
    {
        $result = $this->validator->validate(['response' => ['Error' => 'no code at all']]);
        $this->assertFalse($result->isValid());
    }

    public function testEmptyResponseIsInvalid(): void
    {
        $result = $this->validator->validate(['response' => []]);
        $this->assertFalse($result->isValid());
    }

    public function testNonArrayResponseFieldFailsCleanly(): void
    {
        $result = $this->validator->validate(['response' => 'not-an-array']);
        $this->assertFalse($result->isValid());
    }
}
