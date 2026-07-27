<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Observer;

use Gtstudio\Ebizcharge\Gateway\Config\Config;
use Gtstudio\Ebizcharge\Observer\ValidateSubscriptionCheckout;
use Gtstudio\Ebizcharge\Setup\Patch\Data\AddSubscriptionAttributesPatch;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Payment;
use Magento\Vault\Model\Ui\VaultConfigProvider;
use PHPUnit\Framework\TestCase;

class ValidateSubscriptionCheckoutTest extends TestCase
{
    public function testRejectsSubscriptionWithoutSavedCardIntentBeforePayment(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('require an EBizCharge card');

        $this->validator()->execute($this->observer($this->quote(false)));
    }

    public function testAllowsLoggedInNewCardCheckoutWithVaultEnabled(): void
    {
        $this->validator()->execute($this->observer($this->quote(true)));
        $this->addToAssertionCount(1);
    }

    public function testAllowsVirtualSubscriptionProduct(): void
    {
        $this->validator()->execute(
            $this->observer($this->quote(true, false, ProductType::TYPE_VIRTUAL))
        );
        $this->addToAssertionCount(1);
    }

    public function testRejectsUnsupportedSubscriptionProductType(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('uses an unsupported product type');

        $this->validator()->execute(
            $this->observer($this->quote(true, false, 'configurable'))
        );
    }

    public function testRenewalMarkerBypassesNewSubscriptionValidation(): void
    {
        $quote = $this->quote(false, true);

        $this->validator()->execute($this->observer($quote));
        $this->addToAssertionCount(1);
    }

    private function validator(): ValidateSubscriptionCheckout
    {
        return new ValidateSubscriptionCheckout($this->createMock(ProductRepositoryInterface::class));
    }

    private function quote(
        bool $saveCard,
        bool $renewal = false,
        string $productType = ProductType::TYPE_SIMPLE
    ): Quote {
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethod', 'getAdditionalInformation'])
            ->getMock();
        $payment->method('getMethod')->willReturn(Config::METHOD_CODE);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static fn (?string $key = null): mixed => match ($key) {
                'gtstudio_recurring_charge' => $renewal,
                VaultConfigProvider::IS_ACTIVE_CODE => $saveCard,
                default => null,
            }
        );

        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'getTypeId'])
            ->getMock();
        $product->method('getData')->willReturnCallback(
            static fn (string $key): mixed => match ($key) {
                AddSubscriptionAttributesPatch::ATTR_SUBSCRIBABLE => true,
                AddSubscriptionAttributesPatch::ATTR_FREQUENCY => 'monthly',
                default => null,
            }
        );
        $product->method('getTypeId')->willReturn($productType);

        $item = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getProduct'])
            ->getMock();
        $item->method('getProduct')->willReturn($product);

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getAllVisibleItems'])
            ->addMethods(['getCustomerId'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getCustomerId')->willReturn(123);
        if ($renewal) {
            $quote->expects($this->never())->method('getAllVisibleItems');
        } else {
            $quote->method('getAllVisibleItems')->willReturn([$item]);
        }
        return $quote;
    }

    private function observer(Quote $quote): Observer
    {
        return new Observer(['event' => new DataObject(['quote' => $quote])]);
    }
}
