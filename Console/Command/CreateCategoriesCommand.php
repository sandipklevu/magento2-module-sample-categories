<?php
declare(strict_types=1);

namespace SandipKlevu\SampleCategories\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\Module\Dir\Reader as ModuleReader;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Registry;

class CreateCategoriesCommand extends Command
{
    private const OPTION_CLEAN = 'clean';

    private $categoryFactory;
    private $categoryResource;
    private $categoryCollectionFactory;
    private $storeManager;
    private $moduleReader;
    private $serializer;
    private $registry;

    /**
     * @param CategoryFactory $categoryFactory
     * @param CategoryResource $categoryResource
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param ModuleReader $moduleReader
     * @param Json $serializer
     * @param Registry $registry
     * @param string|null $name
     */
    public function __construct(
        CategoryFactory           $categoryFactory,
        CategoryResource          $categoryResource,
        CategoryCollectionFactory $categoryCollectionFactory,
        StoreManagerInterface     $storeManager,
        ModuleReader              $moduleReader,
        Json                      $serializer,
        Registry                  $registry,
        ?string                    $name = null
    )
    {
        parent::__construct($name);
        $this->categoryFactory = $categoryFactory;
        $this->categoryResource = $categoryResource;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->storeManager = $storeManager;
        $this->moduleReader = $moduleReader;
        $this->serializer = $serializer;
        $this->registry = $registry;
    }

    protected function configure()
    {
        $this->setName('sample:categories:create')
            ->setDescription('Generates sample category hierarchy recursively from etc/categories.json')
            ->addOption(
                self::OPTION_CLEAN,
                'c',
                InputOption::VALUE_NONE,
                'Remove existing matching sample categories before creating new ones'
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->registry->registry('isSecureArea')) {
            $this->registry->register('isSecureArea', true);
        }

        $categoriesHierarchy = $this->getCategoriesFromJson();

        if (empty($categoriesHierarchy)) {
            $output->writeln('<error>No categories found in etc/categories.json</error>');
            return Command::FAILURE;
        }

        if ($input->getOption(self::OPTION_CLEAN)) {
            $output->writeln('<comment>Cleaning up existing sample categories...</comment>');
            $this->cleanExistingCategories($categoriesHierarchy, $output);
        }

        $output->writeln('<info>Starting Category Generation...</info>');
        $rootCategoryId = $this->storeManager->getStore()->getRootCategoryId();
        $this->processCategoriesRecursively($categoriesHierarchy, $rootCategoryId, $output);

        $output->writeln('<info>Process completed!</info>');
        return Command::SUCCESS;
    }

    private function cleanExistingCategories(array $categoriesHierarchy, OutputInterface $output)
    {
        $rootCategoryNames = array_column($categoriesHierarchy, 'name');

        if (empty($rootCategoryNames)) {
            return;
        }

        $collection = $this->categoryCollectionFactory->create()
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('name', ['in' => $rootCategoryNames]);

        foreach ($collection as $category) {
            try {
                $categoryName = $category->getName();
                $categoryId = $category->getId();
                $this->categoryResource->delete($category);
                $output->writeln("<info>Deleted Category:</info> {$categoryName} (ID: {$categoryId})");
            } catch (\Exception $e) {
                $output->writeln("<error>Failed to delete category {$category->getName()}: {$e->getMessage()}</error>");
            }
        }
    }

    private function processCategoriesRecursively(array $categories, $parentId, OutputInterface $output)
    {
        foreach ($categories as $categoryData) {
            // Check if category already exists under the parent
            $existingCategory = $this->getExistingCategoryByNameAndParent($categoryData['name'], $parentId);

            if ($existingCategory) {
                $output->writeln("<comment>Skipped (Already Exists):</comment> {$categoryData['name']} (ID: {$existingCategory->getId()})");
                $categoryId = $existingCategory->getId();
            } else {
                $createdCategory = $this->createCategory($categoryData, $parentId);
                $output->writeln("<info>Created Category:</info> {$categoryData['name']} (ID: {$createdCategory->getId()})");
                $categoryId = $createdCategory->getId();
            }

            if (!empty($categoryData['children']) && is_array($categoryData['children'])) {
                $this->processCategoriesRecursively($categoryData['children'], $categoryId, $output);
            }
        }
    }

    private function getExistingCategoryByNameAndParent(string $name, $parentId): ?Category
    {
        $collection = $this->categoryCollectionFactory->create()
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('name', $name)
            ->addAttributeToFilter('parent_id', $parentId)
            ->setPageSize(1);

        $category = $collection->getFirstItem();
        return $category->getId() ? $category : null;
    }

    private function createCategory(array $data, $parentId)
    {
        $category = $this->categoryFactory->create();
        $category->setName($data['name']);
        $category->setParentId($parentId);

        $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        $category->setIsActive($isActive);
        $category->setIncludeInMenu($isActive);
        $category->setIsAnchor(isset($data['is_anchor']) ? (bool)$data['is_anchor'] : true);
        $category->setDescription($data['description'] ?? '');

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

        if (!empty($data['landing_page'])) {
            $category->setLandingPage((int)$data['landing_page']);
        }

        if (!empty($data['image'])) {
            $category->setImage($data['image'], null, true, false);
        }

        if (!empty($data['thumbnail'])) {
            $category->setData('thumbnail', $data['thumbnail']);
        }

        $this->categoryResource->save($category);
        return $category;
    }

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
}