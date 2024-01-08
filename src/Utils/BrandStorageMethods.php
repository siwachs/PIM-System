<?php

namespace App\Utils;

use Pimcore\Model\DataObject\Brand;



class BrandStorageMethods
{
    // Properties for tracking import status
    public static $totalObjects = 0;
    public static $partialFailed = 0;
    public static $completelyFailed = 0;
    public static $fullySuccessful = 0;
    public static $errorLog = "";

    // Constants for log summary
    const ASSET_FILENAME = "Brands Import Summary.txt";
    const ASSET_FILE_PATH = "/Logs/Brands/Brands Import Summary.txt";
    const PARENT_DIRECTORY_PATH = "/Logs/Brands";

    // Constants for error log
    const ERROR_ASSET_FILENAME = "Brands Error Report.txt";
    const ERROR_ASSET_FILE_PATH = "/Logs/Brands/Brands Error Report.txt";
    const ERROR_PARENT_DIRECTORY_PATH = "/Logs/Brands";

    // Constants for mapping data
    const LOGO_PREFIX = '/LOGOS/';
    const OBJECT_PATH = '/Brands/';
    const PIMCORE_FOLDER_PATH = "/Brands";

    // Constants for validating data
    const YEAR_FIELD_NAME = 'Year Founded';
    const SOCIAL_MEDIA_FIELDS = ['Social Media Link', 'Social Media Text', 'Social Media Title'];
    const WEBSITE_LINK_FIELDS = ['Website Link', 'Website Link Text', 'Website Link Title'];

    private static function mapData($brandName, $brand, $countryCode, $brandObj)
    {
        $brandObj->setLogo(Utils::getAsset(self::LOGO_PREFIX . $brand['Logo']));
        $brandObj->setName($brand['Name'], $countryCode);
        $brandObj->setContact($brand['Contact'], $countryCode);
        $brandObj->setCountry($brand['Country']);
        $brandObj->setSocialMediaLink(Utils::getSocialMediaLinkObject(
            $brand[self::SOCIAL_MEDIA_FIELDS[0]],
            $brand[self::SOCIAL_MEDIA_FIELDS[1]],
            $brand[self::SOCIAL_MEDIA_FIELDS[2]]
        ));
        $brandObj->setWebsiteLink(Utils::getSocialMediaLinkObject(
            $brand[self::WEBSITE_LINK_FIELDS[0]],
            $brand[self::WEBSITE_LINK_FIELDS[1]],
            $brand[self::WEBSITE_LINK_FIELDS[2]]
        ));
        if (Utils::validateNumber($brand[self::YEAR_FIELD_NAME], 'year')) {
            $brandObj->setYearFounded($brand[self::YEAR_FIELD_NAME]);
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

    /**
     * Fetch a brand based on provided brand name
     *
     * @param string $brandName Brand name
     * @return Brand|null Returns a Brand object or null if not found
     */
    private static function fetchBrand($brandName)
    {
        return Brand::getByPath(self::OBJECT_PATH . $brandName);
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
        $parentId = Utils::getOrCreateFolderIdByPath(self::PIMCORE_FOLDER_PATH, 1);
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
