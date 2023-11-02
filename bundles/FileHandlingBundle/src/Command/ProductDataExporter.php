<?php

namespace FileHandlingBundle\Command;

use Pimcore\Model\DataObject;
use Pimcore\Model\Asset;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


class ProductDataExporter extends Command
{
    protected static $defaultName = 'app:export-products';
    private $appKernel;

    public function __construct(KernelInterface $appKernel)
    {
        parent::__construct();
        $this->appKernel = $appKernel;
    }

    protected function configure()
    {
        $this->setDescription('For Export Products from Pimcore.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $projectRoot = $this->appKernel->getProjectDir();
            $products = new DataObject\Product\Listing();
            dump($products);

            $spreadsheet = new Spreadsheet();
            $activeWorksheet = $spreadsheet->getActiveSheet();

            $activeWorksheet->getColumnDimension('A')->setWidth(30);
            $activeWorksheet->getColumnDimension('B')->setWidth(30);
            $activeWorksheet->getColumnDimension('C')->setWidth(30);
            $activeWorksheet->getColumnDimension('D')->setWidth(30);
            $activeWorksheet->getColumnDimension('E')->setWidth(50);
            $activeWorksheet->getColumnDimension('F')->setWidth(50);

            $activeWorksheet->setCellValue('A1', 'Name')->getStyle('A1')->getFont()->setBold(true);
            $activeWorksheet->setCellValue('B1', 'Description')->getStyle('B1')->getFont()->setBold(true);
            $activeWorksheet->setCellValue('C1', 'Stock Availability')->getStyle('C1')->getFont()->setBold(true);
            $activeWorksheet->setCellValue('D1', 'Size')->getStyle('D1')->getFont()->setBold(true);
            $activeWorksheet->setCellValue('E1', 'Categories')->getStyle('D1')->getFont()->setBold(true);
            $activeWorksheet->setCellValue('F1', 'Categories Description')->getStyle('D1')->getFont()->setBold(true);

            $writer = new Xlsx($spreadsheet);

            foreach ($products as $index => $product) {
                $row = $index + 2;
                $activeWorksheet->setCellValue('A' . $row, $product->getName());
                $activeWorksheet->setCellValue('B' . $row, $product->getDescription());
                $activeWorksheet->setCellValue('C' . $row, $product->getStockAvailability());
                $activeWorksheet->setCellValue('D' . $row, $product->getSize());

                $categoriesString = '';
                $categoriesDescriptionString = '';
                $categories = $product->getCategories();

                foreach ($categories as $category) {
                    $categoriesString = $categoriesString . $category->getName() . ', ';
                    $categoriesDescriptionString = $categoriesDescriptionString . $category->getDescription() . ', ';
                }

                $categoriesString = rtrim($categoriesString, ', ');
                $categoriesDescriptionString = rtrim($categoriesDescriptionString, ', ');

                $activeWorksheet->setCellValue('E' . $row, $categoriesString);
                $activeWorksheet->setCellValue('F' . $row, $categoriesDescriptionString);
            }
            $writer->save('products.xlsx');

            $productsAsset = new Asset();
            $productsAsset->setFilename("products.xlsx");
            $productsAsset->setData(file_get_contents($projectRoot . "/products.xlsx"));
            $productsAsset->setParent(Asset::getByPath("/"));

            $productsAsset->save(["versionNote" => "Harcoded version note."]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
