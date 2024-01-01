<?php

namespace App\Utils;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Pimcore\Model\DataObject\Product\Listing;
use Pimcore\Model\DataObject\Product;
use \Pimcore\Model\DataObject\Data\Video;


class ProductExportMethods
{
    /**
     * Sets headers (column names) in an Excel spreadsheet.
     *
     * @param Worksheet $sheet The worksheet object to set headers on.
     * @return void
     */
    public static function setExcelHeaders(Worksheet $sheet)
    {
        $headers = [
            'SKU', 'Name', 'Description', 'Country', 'Brand', 'Manufacturer', 'Category', 'Sub Categories',
            'Color', 'Energy Rating', 'Master Image Link', 'Images Link', 'Video Link', 'Video Poster',
            'Video Title', 'Video Description', 'Quantity Sold', 'Revenue', 'Product Availability', 'Rating',
            'Base Price', 'Selling Price', 'Delivery Charges', 'Tax', 'Discount', 'Length', 'Breadth', 'Height',
            'Dimension Unit', 'Size', 'Size Unit', 'Weight', 'Weight Unit', 'Model Number', 'Model Year',
            'Model Name', 'Hardware Interface', 'Power Source', 'Voltage', 'Wattage', 'Country Of Origin',
            'Batteries Required', 'Batteries Included', 'Camera', 'Motherboard', 'Operating System', 'Processor',
            'RAM', 'ROM', 'Screen', 'Sensors Set', 'Speakers', 'SSD', 'HDD', 'Connectivity Technolgies'
        ];

        $column = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($column . '1', $header);
            $column++;
        }
    }

    /**
     * @param array|null $array
     * @return string|null
     */
    private static function getFirstKeyOrNull(?array $array): ?string
    {
        if (!empty($array)) {
            return $array[0]->getKey();
        }

        return null;
    }

    /**
     * Export video-related data to Excel sheet.
     *
     * @param Video|null $video Video object to export
     * @param Worksheet $sheet Excel sheet object
     * @param int $row Row number in the Excel sheet
     * @return void
     */
    private static function exportVideoDataToExcel($video, Worksheet $sheet, int $row)
    {
        if ($video !== null) {
            $videoData = $video->getData();
            if ($videoData !== null) {
                $sheet->setCellValue('M' . $row, $videoData->getFullPath());
                $poster = $video->getPoster();
                if (!empty($poster)) {
                    $sheet->setCellValue('N' . $row, $poster->getFullPath());
                }
                $sheet->setCellValue('O' . $row, $video->getTitle());
                $sheet->setCellValue('P' . $row, $video->getDescription());
            }
        }
    }

    /**
     * Write Base data to the specified Excel sheet.
     *
     * @param Product $product product object
     * @param Worksheet $sheet Excel sheet object
     * @param int $row Row Number
     * @return void
     */
    private static function writeBaseDataToExcle(Product $product, Worksheet $sheet, int $row)
    {
        $sheet->setCellValue('A' . $row, $product->getSku());
        $sheet->setCellValue('B' . $row, $product->getName());
        $sheet->setCellValue('C' . $row, $product->getDescription());
        $sheet->setCellValue('D' . $row, $product->getCountry());

        // Get Brand
        $brand = self::getFirstKeyOrNull($product->getBrand());
        $sheet->setCellValue('E' . $row, $brand === null ? "" : $brand);

        // Get Manufacturer
        $manufacturer = self::getFirstKeyOrNull($product->getManufacturer());
        $sheet->setCellValue('F' . $row, $manufacturer === null ? "" : $manufacturer);

        // Get Category
        $category = self::getFirstKeyOrNull($product->getCategory());
        $sheet->setCellValue('G' . $row, $category === null ? "" : $category);

        // Get Sub Categories
        $subCategories = array_map(function ($category) {
            return $category->getKey();
        }, $product->getSubCategory());
        $subCategoriesString = implode(', ', $subCategories);
        $sheet->setCellValue('H' . $row, $subCategoriesString);

        $sheet->setCellValue('I' . $row, $product->getColor());
        $sheet->setCellValue('J' . $row, $product->getEnergyRating());
    }

    /**
     * Write Asset data to the specified Excel sheet.
     *
     * @param Product $product product object
     * @param Worksheet $sheet Excel sheet object
     * @param int $row Row Number
     * @return void
     */
    private static function writeAssetDataToExcle(Product $product, Worksheet $sheet, int $row)
    {
        $sheet->setCellValue('K' . $row, $product->getMasterImage());

        // Fetch Image Gallery on L.

        $video = $product->getVideo();
        self::exportVideoDataToExcel($video, $sheet, $row);
    }

    /**
     * Write Sales And Pricing data to the specified Excel sheet.
     *
     * @param Product $product product object
     * @param Worksheet $sheet Excel sheet object
     * @param int $row Row Number
     * @return void
     */
    private static function writeSalesAndPricingDataToExcle(Product $product, Worksheet $sheet, int $row)
    {
        $sheet->setCellValue('Q' . $row, $product->getQuantitySold());
        $sheet->setCellValue('R' . $row, $product->getRevenue());
        $sheet->setCellValue('S' . $row, $product->getProductAvailablity());
        $sheet->setCellValue('T' . $row, $product->getRating());
        $sheet->setCellValue('U' . $row, $product->getBasePrice());
        $sheet->setCellValue('V' . $row, $product->getSellingPrice());
        $sheet->setCellValue('W' . $row, $product->getDeliveryCharges());
        $sheet->setCellValue('X' . $row, $product->getTax());
        $sheet->setCellValue('Y' . $row, $product->getDiscount());
    }

    /**
     * Write Measurements data to the specified Excel sheet.
     *
     * @param Product $product product object
     * @param Worksheet $sheet Excel sheet object
     * @param int $row Row Number
     * @return void
     */
    private static function writeMeasurementsDataToExcle(Product $product, Worksheet $sheet, int $row)
    {
        $sheet->setCellValue('Z' . $row, $product->getLength());
        $sheet->setCellValue('AA' . $row, $product->getBreadth());
        $sheet->setCellValue('AB' . $row, $product->getHeight());
        $sheet->setCellValue('AC' . $row, $product->getDimensionUnit());
        $sheet->setCellValue('AD' . $row, $product->getSize());
        $sheet->setCellValue('AE' . $row, $product->getSizeUnit());
        $sheet->setCellValue('AF' . $row, $product->getWeight());
        $sheet->setCellValue('AG' . $row, $product->getWeightUnit());
    }

    /**
     * Write Technical data to the specified Excel sheet.
     *
     * @param Product $product product object
     * @param Worksheet $sheet Excel sheet object
     * @param int $row Row Number
     * @return void
     */
    private static function writeTechnicalDataToExcle(Product $product, Worksheet $sheet, int $row)
    {
        $sheet->setCellValue('AH' . $row, $product->getModelNumber());
        $sheet->setCellValue('AI' . $row, $product->getModelYear());
        $sheet->setCellValue('AJ' . $row, $product->getModelName());
        $sheet->setCellValue('AK' . $row, $product->getHardwareInterface());
        $sheet->setCellValue('AL' . $row, $product->getPowerSource());
        $sheet->setCellValue('AM' . $row, $product->getVoltage());
        $sheet->setCellValue('AN' . $row, $product->getWattage());
        $sheet->setCellValue('AO' . $row, $product->getCountryOfOrigin());
        $sheet->setCellValue('AP' . $row, $product->getBatteriesRequired());
        $sheet->setCellValue('AQ' . $row, $product->getBatteriesIncluded());
    }


    /**
     * Write Advance Technica; data to the specified Excel sheet.
     *
     * @param Product $product product object
     * @param Worksheet $sheet Excel sheet object
     * @param int $row Row Number
     * @return void
     */
    private static function writeAdvanceTechnicalDataToExcle(Product $product, Worksheet $sheet, int $row)
    {
        // Get Camera
        $camera = self::getFirstKeyOrNull($product->getCamera());
        $sheet->setCellValue('AR' . $row, $camera === null ? "" : $camera);

        // Get Motherboard
        $motherboard = self::getFirstKeyOrNull($product->getMotherboard());
        $sheet->setCellValue('AS' . $row, $motherboard === null ? "" : $motherboard);

        // Get Operating System
        $os = self::getFirstKeyOrNull($product->getOperatingSystem());
        $sheet->setCellValue('AT' . $row, $os === null ? "" : $os);

        // Get Processor
        $processor = self::getFirstKeyOrNull($product->getProcessor());
        $sheet->setCellValue('AU' . $row, $processor === null ? "" : $processor);

        // Get RAM
        $ram = self::getFirstKeyOrNull($product->getRam());
        $sheet->setCellValue('AV' . $row, $ram === null ? "" : $ram);

        // Get ROM
        $rom = self::getFirstKeyOrNull($product->getRom());
        $sheet->setCellValue('AW' . $row, $rom === null ? "" : $rom);

        // Get Screen
        $screen = self::getFirstKeyOrNull($product->getScreen());
        $sheet->setCellValue('AX' . $row, $screen === null ? "" : $screen);

        // Get Sensors Set
        $sensors = self::getFirstKeyOrNull($product->getSensorsSet());
        $sheet->setCellValue('AY' . $row, $sensors === null ? "" : $sensors);

        // Get Speakers
        $speakers = self::getFirstKeyOrNull($product->getSpeakers());
        $sheet->setCellValue('AZ' . $row, $speakers === null ? "" : $speakers);

        // Get SSD
        $ssd = self::getFirstKeyOrNull($product->getSSD());
        $sheet->setCellValue('BA' . $row, $ssd === null ? "" : $ssd);

        // Get HDD
        $hdd = self::getFirstKeyOrNull($product->getHDD());
        $sheet->setCellValue('BB' . $row, $hdd === null ? "" : $hdd);

        $sheet->setCellValue('BC' . $row, implode(", ", $product->getConnectivityTechnolgies()));
    }


    /**
     * Write product data to the specified Excel sheet.
     *
     * @param Listing $products Listing of products
     * @param Worksheet $sheet Excel sheet object
     * @return void
     */
    public static function writeProductsToExcel(Listing $products, Worksheet $sheet)
    {
        $row = 2;
        foreach ($products as $product) {
            try {
                self::writeBaseDataToExcle($product, $sheet, $row);
                self::writeAssetDataToExcle($product, $sheet, $row);
                self::writeSalesAndPricingDataToExcle($product, $sheet, $row);
                self::writeMeasurementsDataToExcle($product, $sheet, $row);
                self::writeTechnicalDataToExcle($product, $sheet, $row);
                self::writeAdvanceTechnicalDataToExcle($product, $sheet, $row);
            } catch (\Exception $e) {
                dump($e->getMessage());
            }

            $row++;
        }

        for ($column = 'A'; $column !== 'BD'; $column++) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
            $sheet->getStyle($column . '1')->getFont()->setBold(true);
        }
    }
}
