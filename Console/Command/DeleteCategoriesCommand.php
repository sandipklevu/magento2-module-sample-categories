<?php
declare(strict_types=1);

namespace SandipKlevu\SampleCategories\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Module\Dir\Reader as ModuleReader;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Registry;

class DeleteCategoriesCommand extends Command
{
    private $categoryResource;
    private $categoryCollectionFactory;
    private $moduleReader;
    private $serializer;
    private $registry;

    /**
     * @param CategoryResource $categoryResource
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ModuleReader $moduleReader
     * @param Json $serializer
     * @param Registry $registry
     * @param string|null $name
     */
    public function __construct(
        CategoryResource          $categoryResource,
        CategoryCollectionFactory $categoryCollectionFactory,
        ModuleReader              $moduleReader,
        Json                      $serializer,
        Registry                  $registry,
        ?string                   $name = null
    )
    {
        parent::__construct($name);
        $this->categoryResource = $categoryResource;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->moduleReader = $moduleReader;
        $this->serializer = $serializer;
        $this->registry = $registry;
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('sample:categories:delete')
            ->setDescription('Deletes all sample categories defined in etc/categories.json');
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return mixed
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->registry->registry('isSecureArea')) {
            $this->registry->register('isSecureArea', true);
        }

        $categoriesHierarchy = $this->getCategoriesFromJson();

        if (empty($categoriesHierarchy)) {
            $output->writeln('<error>No categories found in etc/categories.json</error>');
            return Command::FAILURE;
        }

        $rootCategoryNames = array_column($categoriesHierarchy, 'name');

        $collection = $this->categoryCollectionFactory->create()
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('name', ['in' => $rootCategoryNames]);

        if ($collection->count() === 0) {
            $output->writeln('<comment>No matching sample categories found to delete.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Deleting sample categories...</info>');

        foreach ($collection as $category) {
            try {
                $categoryName = $category->getName();
                $categoryId = $category->getId();
                $this->categoryResource->delete($category);
                $output->writeln("<info>Successfully Deleted:</info> {$categoryName} (ID: {$categoryId})");
            } catch (\Exception $e) {
                $output->writeln("<error>Failed to delete {$category->getName()}: {$e->getMessage()}</error>");
            }
        }

        $output->writeln('<info>Category cleanup completed!</info>');
        return Command::SUCCESS;
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
}