<?php

namespace SandipKlevu\SampleCategories\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Category;

class CreateSampleCategories implements DataPatchInterface
{
    private $moduleDataSetup;
    private $categoryFactory;
    private $categoryResource;
    private $storeManager;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CategoryFactory          $categoryFactory,
        CategoryResource         $categoryResource,
        StoreManagerInterface    $storeManager
    )
    {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->categoryFactory = $categoryFactory;
        $this->categoryResource = $categoryResource;
        $this->storeManager = $storeManager;
    }

    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $rootCategoryId = $this->storeManager->getStore()->getRootCategoryId();

        $categoriesHierarchy = [
            [
                'name' => 'E-Commerce & Retail',
                'description' => 'Explore featured items, deals, and daily discounts across our general retail catalog.',
                'children' => [
                    ['name' => 'Daily Deals & Flash Sales', 'description' => 'Limited-time discounts on trending products.'],
                    ['name' => 'Featured Collections', 'description' => 'Curated items and best-selling lifestyle products.']
                ]
            ],
            [
                'name' => 'Electronics',
                'description' => 'Upgrade your daily tech with cutting-edge smartphones, audio systems, and smart home gadgets.',
                'children' => [
                    ['name' => 'Computers & Accessories', 'description' => 'High-performance laptops, desktop setups, and monitors.'],
                    ['name' => 'Audio & Headphones', 'description' => 'Immersive wireless headphones and bluetooth speakers.'],
                    ['name' => 'Smart Home & Wearables', 'description' => 'Smartwatches, fitness trackers, and security devices.']
                ]
            ],
            [
                'name' => 'Clothing & Apparel',
                'description' => 'Discover modern wardrobe essentials, seasonal fashion trends, accessories, and casual wear.',
                'children' => [
                    ['name' => 'Men\'s Fashion', 'description' => 'Casual shirts, trousers, jackets, and formal wear.'],
                    ['name' => 'Women\'s Fashion', 'description' => 'Chic dresses, comfortable loungewear, and daily tops.'],
                    ['name' => 'Footwear & Accessories', 'description' => 'Sneakers, leather boots, backpacks, and belts.']
                ]
            ],
            [
                'name' => 'Home & Kitchen',
                'description' => 'Furnish your living spaces with elegant furniture, cookware, and smart appliances.',
                'children' => [
                    ['name' => 'Kitchen & Dining', 'description' => 'Non-stick cookware sets, chef knives, and tableware.'],
                    ['name' => 'Furniture & Decor', 'description' => 'Ergonomic office chairs, coffee tables, and lighting.']
                ]
            ],
            [
                'name' => 'Beauty & Personal Care',
                'description' => 'Nourish your skin, hair, and body with premium skincare essentials and cosmetics.',
                'children' => [
                    ['name' => 'Skincare & Grooming', 'description' => 'Gentle facial cleansers, hydration serums, and sunscreens.'],
                    ['name' => 'Hair Care & Styling', 'description' => 'Nourishing shampoos, conditioners, and styling tools.']
                ]
            ],
            [
                'name' => 'Sports & Outdoors',
                'description' => 'Gear up for active adventures with quality fitness equipment and outdoor camping gear.',
                'children' => [
                    ['name' => 'Exercise & Fitness', 'description' => 'Dumbbells, resistance bands, and yoga mats.'],
                    ['name' => 'Camping & Hiking', 'description' => 'Weatherproof tents, sleeping bags, and hiking backpacks.']
                ]
            ],
        ];

        foreach ($categoriesHierarchy as $parentData) {
            $parentCategory = $this->createCategory($parentData, $rootCategoryId);

            if (!empty($parentData['children']) && is_array($parentData['children'])) {
                foreach ($parentData['children'] as $childData) {
                    $this->createCategory($childData, $parentCategory->getId());
                }
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    private function createCategory(array $data, $parentId)
    {
        $category = $this->categoryFactory->create();
        $category->setName($data['name']);
        $category->setParentId($parentId);
        $category->setIsActive(true);
        $category->setIncludeInMenu(true);
        $category->setDescription($data['description'] ?? '');
        $category->setDisplayMode(Category::DM_PRODUCT);
        $category->setIsAnchor(true);

        $this->categoryResource->save($category);
        return $category;
    }

    public static function getDependencies()
    {
        return [];
    }

    public static function getAliases()
    {
        return [];
    }
}