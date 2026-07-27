<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Test\Unit\Model\Adminhtml\Source;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Gtstudio\Ebizcharge\Model\Adminhtml\Source\SubscriptionFrequency;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Framework\Data\OptionSourceInterface;
use PHPUnit\Framework\TestCase;

class SubscriptionFrequencyTest extends TestCase
{
    public function testSupportsEavAttributeAndUiOptionSourceContracts(): void
    {
        $source = new SubscriptionFrequency();
        $attribute = $this->createMock(AbstractAttribute::class);

        $this->assertInstanceOf(AbstractSource::class, $source);
        $this->assertInstanceOf(OptionSourceInterface::class, $source);
        $this->assertSame($source, $source->setAttribute($attribute));
        $this->assertSame($attribute, $source->getAttribute());
        $this->assertSame($source->getAllOptions(), $source->toOptionArray());
    }

    public function testProvidesEverySupportedFrequencyToEavAndUiConsumers(): void
    {
        $source = new SubscriptionFrequency();
        $expectedValues = [
            SubscriptionInterface::FREQUENCY_DAILY,
            SubscriptionInterface::FREQUENCY_WEEKLY,
            SubscriptionInterface::FREQUENCY_BIWEEKLY,
            SubscriptionInterface::FREQUENCY_MONTHLY,
            SubscriptionInterface::FREQUENCY_BIMONTHLY,
            SubscriptionInterface::FREQUENCY_QUARTERLY,
            SubscriptionInterface::FREQUENCY_BIANNUALLY,
            SubscriptionInterface::FREQUENCY_ANNUALLY,
        ];

        $this->assertSame($expectedValues, array_column($source->getAllOptions(), 'value'));
        $this->assertSame($expectedValues, array_keys($source->toArray()));
        $this->assertSame(
            'Monthly',
            (string) $source->getOptionText(SubscriptionInterface::FREQUENCY_MONTHLY)
        );
        $this->assertFalse($source->getOptionText('unsupported'));
    }
}
