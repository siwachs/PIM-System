<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Utils\Utils;
use App\Utils\ProductExportMethods;
use Pimcore\Model\DataObject\Product;

class ExportProductsCommand extends Command
{
    protected static $defaultName = 'export:products';
    const FILE_FORMAT = '.xlsx';

    protected function configure()
    {
        $this->setDescription('Exports products data')
            ->addArgument('file-name', InputArgument::REQUIRED, 'Specify file name')
            ->addArgument('country-code', InputArgument::REQUIRED, 'Specify the Country code');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Exporting products data...');
        $fileName = $input->getArgument('file-name');
        $countryCode = $input->getArgument('country-code');
        if (empty($fileName)) {
            throw new \InvalidArgumentException('File name must be provided');
        }
        if (empty($countryCode)) {
            throw new \InvalidArgumentException('Country code must be provided');
        }

        try {
            $products = new Product\Listing();
            $products->setLocale($countryCode);

            // Prepare File
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            ProductExportMethods::setExcelHeaders($sheet);
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

            // Load Data into file
            ProductExportMethods::writeProductsToExcel($products, $sheet);

            // Save File
            $localPath = PIMCORE_PROJECT_ROOT . '/' . $fileName . self::FILE_FORMAT;
            $writer->save($localPath);
            Utils::uploadToAssets(
                $fileName . self::FILE_FORMAT,
                '/Imports/' . $fileName . self::FILE_FORMAT,
                '/Imports',
                $localPath
            );
            if (file_exists($localPath)) {
                unlink($localPath);
            }
            $output->writeln('Products export completed.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
