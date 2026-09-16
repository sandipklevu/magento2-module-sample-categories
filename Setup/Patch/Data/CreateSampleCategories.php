<?php
namespace SandipKlevu\SampleCategories\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\Module\Dir\Reader as ModuleReader;
use Magento\Framework\Serialize\Serializer\Json;

class CreateSampleCategories implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    /**
     * @var CategoryFactory
     */
    private $categoryFactory;
    /**
     * @var CategoryResource
     */
    private $categoryResource;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ModuleReader
     */
    private $moduleReader;
    /**
     * @var Json
     */
    private $serializer;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CategoryFactory $categoryFactory
     * @param CategoryResource $categoryResource
     * @param StoreManagerInterface $storeManager
     * @param ModuleReader $moduleReader
     * @param Json $serializer
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CategoryFactory $categoryFactory,
        CategoryResource $categoryResource,
        StoreManagerInterface $storeManager,
        ModuleReader $moduleReader,
        Json $serializer
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->categoryFactory = $categoryFactory;
        $this->categoryResource = $categoryResource;
        $this->storeManager = $storeManager;
        $this->moduleReader = $moduleReader;
        $this->serializer = $serializer;
    }

    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $rootCategoryId = $this->storeManager->getStore()->getRootCategoryId();
        $categoriesHierarchy = $this->getCategoriesFromJson();

        // Start recursive category creation starting at the Store Root
        $this->processCategoriesRecursively($categoriesHierarchy, $rootCategoryId);

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * Recursively creates categories and subcategories to infinite depth
     *
     * @param array $categories
     * @param int|string $parentId
     * @return void
     */
    private function processCategoriesRecursively(array $categories, $parentId)
    {
        foreach ($categories as $categoryData) {
            $createdCategory = $this->createCategory($categoryData, $parentId);

            // If this category has children, recurse into them passing the newly created Category ID
            if (!empty($categoryData['children']) && is_array($categoryData['children'])) {
                $this->processCategoriesRecursively($categoryData['children'], $createdCategory->getId());
            }
        }
    }

    /**
     * @return array
     */
    private function getCategoriesFromJson(): array
    {
        $etcDir = $this->moduleReader->getModuleDir('etc', 'SandipKlevu_SampleCategories');
        $filePath = $etcDir . '/categories.json';

        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            return $this->serializer->unserialize($content);
        }

        return [];
    }

    /**
     * @param array $data
     * @param $parentId
     * @return mixed
     */
    private function createCategory(array $data, $parentId)
    {
        $category = $this->categoryFactory->create();
        $category->setName($data['name']);
        $category->setParentId($parentId);

        // Status & Navigation Settings
        $isActive = !isset($data['is_active']) || (bool)$data['is_active'];
        $category->setIsActive($isActive);
        $category->setIncludeInMenu($isActive);
        $category->setIsAnchor(!isset($data['is_anchor']) || (bool)$data['is_anchor']);

        // Content & Descriptions
        $category->setDescription($data['description'] ?? '');

        // Display Mode Options: 'PRODUCTS' (DM_PRODUCT), 'PAGE' (DM_PAGE), 'PRODUCTS_AND_PAGE' (DM_MIXED)
        if (isset($data['display_mode'])) {
            switch ($data['display_mode']) {
                case 'PAGE':
                    $category->setDisplayMode(Category::DM_PAGE);
                    break;
                case 'PRODUCTS_AND_PAGE':
                    $category->setDisplayMode(Category::DM_MIXED);
                    break;
                case 'PRODUCTS':
                default:
                    $category->setDisplayMode(Category::DM_PRODUCT);
                    break;
            }
        } else {
            $category->setDisplayMode(Category::DM_PRODUCT);
        }

        // CMS Block Landing Page ID
        if (!empty($data['landing_page'])) {
            $category->setLandingPage((int)$data['landing_page']);
        }

        // Main Category Image Banner
        if (!empty($data['image'])) {
            $category->setImage($data['image'], null, true, false);
        }

        // Category Thumbnail Image
        if (!empty($data['thumbnail'])) {
            $category->setData('thumbnail', $data['thumbnail']);
        }

        $this->categoryResource->save($category);
        return $category;
    }

    /**
     * @return array
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @return array
     */
    public static function getAliases()
    {
        return [];
    }
}