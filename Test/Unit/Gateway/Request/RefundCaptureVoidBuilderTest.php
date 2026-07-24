<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Gateway\Request;

use Gtstudio\Ebizcharge\Gateway\Request\RefundCaptureVoidBuilder;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use PHPUnit\Framework\TestCase;

class RefundCaptureVoidBuilderTest extends TestCase
{
    public function testBuildsReferenceNumberFromParentTransaction(): void
    {
        $payment = $this->paymentInfo(['parent_transaction_id' => '3232464399']);

        $builder = new RefundCaptureVoidBuilder();

        $this->assertSame(
            ['tran' => ['RefNum' => 3232464399]],
            $builder->build(['payment' => $this->paymentDataObject($payment)])
        );
    }

    public function testBuildsReferenceNumberFromLastTransactionBeforeParent(): void
    {
        $payment = $this->paymentInfo([
            'last_trans_id' => '3232464400',
            'parent_transaction_id' => '3232464399',
        ]);

        $builder = new RefundCaptureVoidBuilder();

        $this->assertSame(
            ['tran' => ['RefNum' => 3232464400]],
            $builder->build(['payment' => $this->paymentDataObject($payment)])
        );
    }

    public function testBareCaptureWithoutOriginalTransactionFailsBeforeSoapRequest(): void
    {
        $payment = $this->paymentInfo();

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unable to find original transaction.');

        (new RefundCaptureVoidBuilder())->build(['payment' => $this->paymentDataObject($payment)]);
    }

    /** @param array<string,mixed> $data */
    private function paymentInfo(array $data = []): InfoInterface
    {
        return new class ($data) extends DataObject implements InfoInterface {
            public function encrypt($data)
            {
                return $data;
            }

            public function decrypt($data)
            {
                return $data;
            }

            public function setAdditionalInformation($key, $value = null)
            {
                $info = (array) $this->getData('additional_information');
                if (is_array($key)) {
                    $info = array_replace($info, $key);
                } else {
                    $info[$key] = $value;
                }
                return $this->setData('additional_information', $info);
            }

            public function hasAdditionalInformation($key = null)
            {
                $info = (array) $this->getData('additional_information');
                return $key === null ? $info !== [] : array_key_exists((string) $key, $info);
            }

            public function unsAdditionalInformation($key = null)
            {
                if ($key === null) {
                    return $this->unsetData('additional_information');
                }
                $info = (array) $this->getData('additional_information');
                unset($info[(string) $key]);
                return $this->setData('additional_information', $info);
            }

            public function getAdditionalInformation($key = null)
            {
                $info = (array) $this->getData('additional_information');
                return $key === null ? $info : ($info[(string) $key] ?? null);
            }

            public function getMethodInstance()
            {
                return null;
            }
        };
    }

    private function paymentDataObject(InfoInterface $payment): PaymentDataObjectInterface
    {
        return new class ($payment) implements PaymentDataObjectInterface {
            public function __construct(private readonly InfoInterface $payment)
            {
            }

            public function getOrder()
            {
                return null;
            }

            public function getPayment()
            {
                return $this->payment;
            }
        };
    }
}
