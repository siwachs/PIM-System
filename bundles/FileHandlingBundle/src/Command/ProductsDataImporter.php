<?php

namespace FileHandlingBundle\Command;

use Pimcore\Model\DataObject;
use FileHandlingBundle\Service\TransformData;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ProductsDataImporter extends Command
{
    protected static $defaultName = 'app:import-products';
    private $appKernel;
    private $transformData;
    public function __construct(KernelInterface $appKernel, TransformData $transformData)
    {
        parent::__construct();
        $this->appKernel = $appKernel;
        $this->transformData = new $transformData;
    }

    protected function configure()
    {
        $this->setDescription('For Import Products in Pimcore.')->addArgument('csvPath', InputArgument::OPTIONAL, 'Path to produts csv', '/csvs/products.csv')->addArgument('parentIdProducts', InputArgument::OPTIONAL, 'The Parent Id of products', 2)->addArgument('parentIdCategories', InputArgument::OPTIONAL, 'The Parent Id of categories', 3)->addArgument('versionNote', InputArgument::OPTIONAL, 'Version note of object', 'Default version note is used');
    }

    private function updateCategory($categoryObject, $data, $versionNote)
    {
        $categoryObject->setName($data['categoryName']);
        $categoryObject->setDescription($data['categoryDescription']);
        $categoryObject->save(["versionNote" => $versionNote]);
    }

    private function createCategory($parentIdCategories, $data, $versionNote)
    {
        $newCategory = new DataObject\Category();
        $newCategory->setKey(\Pimcore\Model\Element\Service::getValidKey($data['categoryName'], 'object'));
        $newCategory->setParentId($parentIdCategories); //1
        $newCategory->setName($data['categoryName']);
        $newCategory->setDescription($data['categoryDescription']);
        $newCategory->save(["versionNote" => $versionNote]);

        return  $newCategory;
    }

    private function updateProduct($productObject, $categoryObject, $data, $versionNote)
    {
        $productObject->setName($data['name']);
        $productObject->setDescription($data['description']);
        $productObject->setStockAvailability($data['stockAvailability']);
        $productObject->setSize($data['size']);

        $productObject->setCategories([$categoryObject]);
        $productObject->save(["versionNote" => $versionNote]);
    }

    private function createProduct($parentIdProducts, $categoryObject, $data, $versionNote)
    {
        $newProduct = new DataObject\Product();
        $newProduct->setKey(\Pimcore\Model\Element\Service::getValidKey($data['name'], 'object'));
        $newProduct->setParentId($parentIdProducts); //2
        $newProduct->setName($data['name']);
        $newProduct->setDescription($data['description']);
        $newProduct->setStockAvailability($data['stockAvailability']);
        $newProduct->setSize($data['size']);

        $newProduct->setCategories([$categoryObject]);
        $newProduct->save(["versionNote" => $versionNote]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $projectRoot = $this->appKernel->getProjectDir();
            $csvFilePath = $projectRoot . $input->getArgument('csvPath');
            $parentIdProducts = $input->getArgument('parentIdProducts');
            $parentIdCategories = $input->getArgument('parentIdCategories');
            $versionNote = $input->getArgument('versionNote');
            $file = fopen($csvFilePath, 'r');
            $headers = fgetcsv($file);

            $mandatoryColumns = ['name', 'productName', 'categoryName'];
            $missingColumns = array_diff($mandatoryColumns, $headers);
            if (!empty($missingColumns)) {
                $output->writeln('<error>' . 'Error:Some of Colums are missing' . '</error>');
                return Command::FAILURE;
            }

            $productsArray = $this->transformData->transformProductsCsvToAssocArray($file, $headers);

            foreach ($productsArray as $data) {
                $categoryObject = DataObject\Category::getByPath('/Categories' . '/' . $data['categoryName']);

                if ($categoryObject) {
                    $this->updateCategory($categoryObject, $data, $versionNote);
                } else {
                    $categoryObject = $this->createCategory($parentIdCategories, $data, $versionNote);
                }

                $productObject = DataObject\Product::getByPath('/Products' . '/' . $data['name']);

                if ($productObject) {
                    $this->updateProduct($productObject, $categoryObject, $data, $versionNote);
                } else {
                    $this->createProduct($parentIdProducts, $categoryObject, $data, $versionNote);
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
