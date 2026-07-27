<?php declare(strict_types=1);

namespace Gtstudio\Ebizcharge\Setup\Patch\Data;

use Gtstudio\Ebizcharge\Api\Data\SubscriptionInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/** Adds product subscription attributes. */
class AddSubscriptionAttributesPatch implements DataPatchInterface
{
    public const ATTR_SUBSCRIBABLE = 'gtstudio_subscribable';
    public const ATTR_FREQUENCY = 'gtstudio_subscription_frequency';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory,
        private readonly CategorySetupFactory $categorySetupFactory
    ) {
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    public function apply(): self
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->addAttribute(
            Product::ENTITY,
            self::ATTR_SUBSCRIBABLE,
            [
                'group' => 'General',
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Subscribable',
                'input' => 'boolean',
                'class' => '',
                'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => 0,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
                'apply_to' => 'simple,virtual,downloadable',
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            self::ATTR_FREQUENCY,
            [
                'group' => 'General',
                'type' => 'varchar',
                'label' => 'Subscription Frequency',
                'input' => 'select',
                'source' => \Gtstudio\Ebizcharge\Model\Adminhtml\Source\SubscriptionFrequency::class,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => SubscriptionInterface::FREQUENCY_MONTHLY,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => 'simple,virtual,downloadable',
            ]
        );

        return $this;
    }
}
