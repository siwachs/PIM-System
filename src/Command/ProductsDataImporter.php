<?php

namespace App\Command;

use App\Service\TransformData;
use Pimcore\Model\DataObject;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProductsDataImporter extends Command
{
    protected static $defaultName = 'app:import-products';
    private $transformData;
    public function __construct(TransformData $transformData)
    {
        parent::__construct();
        $this->transformData = new $transformData;
    }

    protected function configure()
    {
        $this->setDescription('For Import Products in Pimcore')->addArgument('csvPath', InputArgument::REQUIRED, 'Path to produts csv')->addArgument('parentIdProducts', InputArgument::REQUIRED, 'The Parent Id of products')->addArgument('parentIdCategories', InputArgument::REQUIRED, 'The Parent Id of categories');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        try {
            $csvFilePath = $input->getArgument('csvPath');
            $parentIdProducts = $input->getArgument('parentIdProducts');
            $parentIdCategories = $input->getArgument('parentIdCategories');
            $file = fopen($csvFilePath, 'r');
            $headers = fgetcsv($file);

            $mandatoryFields = ['name', 'productName', 'categoryName'];
            $missingFields = array_diff($mandatoryFields, $headers);
            if (!empty($missingFields)) {
                $output->writeln('<error>' . 'Error: Mandatory fields are missing' . '</error>');
                return Command::FAILURE;
            }

            $productsArray = $this->transformData->transformProductsCsvToAssocArray($file, $headers);

            foreach ($productsArray as $data) {
                $categoryObject = DataObject\Category::getById($data['categoryId']);

                if (!$categoryObject) {
                    $newCategory = new DataObject\Category();
                    $newCategory->setKey(\Pimcore\Model\Element\Service::getValidKey($data['categoryName'], 'object'));
                    $newCategory->setParentId($parentIdCategories);
                    $newCategory->setName($data['categoryName']);
                    $newCategory->setDescription($data['categoryDescription']);
                    $newCategory->save(["versionNote" => 'For now a hardcode value is use']);

                    $categoryObject = $newCategory;
                }

                $newProduct = new DataObject\Product();
                $newProduct->setKey(\Pimcore\Model\Element\Service::getValidKey($data['name'], 'object'));
                $newProduct->setParentId($parentIdProducts); //2
                $newProduct->setName($data['name']);
                $newProduct->setDescription($data['description']);
                $newProduct->setStockAvailability($data['stockAvailability']);
                $newProduct->setSize($data['size']);

                $newProduct->setCategories([$categoryObject]);
                $newProduct->save(["versionNote" => 'For now a hardcode value is use']);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
