<?php

namespace App\Utils;

use Pimcore\Model\DataObject\Brand;

class BrandStorageMethods
{
    // Properties for tracking import status
    private static $totalObjects = 0;
    private static $partialFailed = 0;
    private static $completelyFailed = 0;
    private static $fullySuccessful = 0;
    private static $errorLog = "";

    // Constants for log summary
    const ASSET_FILENAME = "Brands Import Summary.txt";
    const ASSET_FILE_PATH = "/Logs/Brands/Brands Import Summary.txt";
    const PARENT_DIRECTORY_PATH = "/Logs/Brands";

    // Constants for error log
    const ERROR_ASSET_FILENAME = "Brands Error Report.txt";
    const ERROR_ASSET_FILE_PATH = "/Logs/Brands/Brands Error Report.txt";
    const ERROR_PARENT_DIRECTORY_PATH = "/Logs/Brands";

    private static function mapData($brandName, $brand, $countryCode, $brandObj)
    {
        $brandObj->setLogo(Utils::getAsset('/LOGOS/' . $brand['Logo']));
        $brandObj->setName($brand['Name'], $countryCode);
        $brandObj->setContact($brand['Contact'], $countryCode);
        $brandObj->setCountry($brand['Country']);
        $brandObj->setSocialMediaLink(Utils::getSocialMediaLinkObject(
            $brand['Social Media Link'],
            $brand['Social Media Text'],
            $brand['Social Media Title']
        ));
        $brandObj->setWebsiteLink(Utils::getSocialMediaLinkObject(
            $brand['Website Link'],
            $brand['Website Link Text'],
            $brand['Website Link Title']
        ));
        if (Utils::isValidYearFounded($brand['Year Founded'] ?? 0)) {
            $brandObj->setYearFounded($brand['Year Founded']);
            self::$fullySuccessful++;
        } else {
            self::$partialFailed++;
            self::$errorLog .= "Warning in " . $brandName . " invalid founded year.\n";
        }
    }

    /**
     * Store Brands
     *
     * @param array $brandArray An array containing brand data
     * @param string $countryCode The country code
     */
    public static function storeBrands($brandArray, $countryCode)
    {
        self::$totalObjects = count($brandArray);

        foreach ($brandArray as $brand) {
            try {
                $brandName = $brand['Object Name'];
                if (empty($brand['Name'])) {
                    self::$completelyFailed++;
                    self::$errorLog .= "Error in " . $brandName . ". The name field is empty.\n";
                    continue;
                }

                $brandObj = self::fetchBrand($brandName);

                if ($brandObj instanceof Brand) {
                    self::updateBrand($brandName, $brand, $countryCode, $brandObj);
                } else {
                    self::createBrand($brandName, $brand, $countryCode);
                }
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
        }

        // Log import summary and error report
        self::logBrandSummary();
    }

    // ...

    /**
     * Fetch a brand based on provided brand name
     *
     * @param string $brandName Brand name
     * @return Brand|null Returns a Brand object or null if not found
     */
    private static function fetchBrand($brandName)
    {
        return Brand::getByPath('/Brands/' . $brandName);
    }

    /**
     * Update an existing brand
     *
     * @param string $brandName Brand name
     * @param array $brand Brand data
     * @param string $countryCode Country code for brand
     * @param Brand $brandObj Existing Brand object
     */
    private static function updateBrand($brandName, $brand, $countryCode, $brandObj)
    {
        self::mapData($brandName, $brand, $countryCode, $brandObj);
        $brandObj->setPublished(false);
        $brandObj->save();
    }

    /**
     * Create a new brand
     *
     * @param string $brandName Brand name
     * @param array $brand Brand data
     * @param string $countryCode Country code for brand
     */
    private static function createBrand($brandName, $brand, $countryCode)
    {
        $newBrand = new Brand();
        $newBrand->setKey(\Pimcore\Model\Element\Service::getValidKey($brandName, 'object'));
        $parentId = Utils::getOrCreateFolderIdByPath("/Brands", 1);
        $newBrand->setParentId($parentId);
        self::mapData($brandName, $brand, $countryCode, $newBrand);
        $newBrand->save();
    }

    /**
     * Log the brand import summary
     */
    private static function logBrandSummary()
    {
        // Log import summary and error report
        Utils::logSummary(
            self::ASSET_FILENAME,
            self::ASSET_FILE_PATH,
            self::PARENT_DIRECTORY_PATH,
            self::$totalObjects,
            self::$partialFailed,
            self::$completelyFailed,
            self::$fullySuccessful
        );

        Utils::logError(
            self::ERROR_ASSET_FILENAME,
            self::ERROR_ASSET_FILE_PATH,
            self::ERROR_PARENT_DIRECTORY_PATH,
            self::$errorLog
        );
    }
}
