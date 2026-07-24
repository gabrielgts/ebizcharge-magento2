<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Gateway\Http;

use Gtstudio\Ebizcharge\Gateway\Http\ResponseNormalizer;
use PHPUnit\Framework\TestCase;

class ResponseNormalizerTest extends TestCase
{
    private ResponseNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ResponseNormalizer();
    }

    public function testUnwrapsRunTransactionResult(): void
    {
        $result = $this->normalizer->normalize([
            'runTransactionResult' => [
                'ResultCode' => 'A',
                'RefNum' => '3232461405',
                'AuthCode' => '877128',
                'AvsResultCode' => 'YYY',
                'CardCodeResultCode' => 'M',
            ],
        ], 'runTransaction');

        $this->assertSame('A', $result['ResultCode']);
        $this->assertSame('3232461405', $result['RefNum']);
        $this->assertSame('877128', $result['AuthCode']);
        $this->assertSame('YYY', $result['AvsResultCode']);
        $this->assertSame('M', $result['CardCodeResultCode']);
    }

    public function testConvertsObjectAndUnwrapsRunCustomerTransactionResult(): void
    {
        $response = (object) [
            'runCustomerTransactionResult' => (object) [
                'ResultCode' => 'A',
                'RefNum' => '3232461500',
            ],
        ];

        $this->assertSame(
            ['ResultCode' => 'A', 'RefNum' => '3232461500'],
            $this->normalizer->normalize($response, 'runCustomerTransaction')
        );
    }

    public function testPreservesAlreadyFlatResponse(): void
    {
        $response = ['ResultCode' => 'E', 'Error' => 'Invalid Card Number'];

        $this->assertSame($response, $this->normalizer->normalize($response, 'runTransaction'));
    }

    public function testPreservesUnknownResponseEnvelope(): void
    {
        $response = ['UnexpectedResult' => ['ResultCode' => 'A']];

        $this->assertSame($response, $this->normalizer->normalize($response, 'runTransaction'));
    }

    public function testPreservesMalformedMethodResult(): void
    {
        $response = ['runTransactionResult' => 'not-an-object'];

        $this->assertSame($response, $this->normalizer->normalize($response, 'runTransaction'));
    }

    public function testWrapsScalarResponseForSafeValidationFailure(): void
    {
        $this->assertSame(['raw' => null], $this->normalizer->normalize(null, 'runTransaction'));
    }

    public function testPreservesScalarProfileResultWrapper(): void
    {
        $response = (object) ['AddCustomerPaymentMethodProfileResult' => '00077'];

        $this->assertSame(
            ['AddCustomerPaymentMethodProfileResult' => '00077'],
            $this->normalizer->normalize($response, 'AddCustomerPaymentMethodProfile')
        );
    }
}
