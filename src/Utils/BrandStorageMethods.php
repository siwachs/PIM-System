<?php

namespace App\Utils;

use Pimcore\Model\DataObject\Brand;
use Pimcore\Model\DataObject\Folder;

class BrandStorageMethods
{
    private static $totalObjects = 0;
    private static $partialFailed = 0;
    private static $completelyFailed = 0;
    private static $fullySuccessful = 0;
    private static $errorLog = "";

    private static function mapData($brandName, $brand, $countryCode, $brandObj)
    {
        $brandObj->setLogo(Utils::getAsset(Utils::getAsset('/LOGOS/' . $brand['Logo'])));
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
                $brandObj = Brand::getByPath('/Brands/' . $brandName);
                if (empty($brand['Name'])) {
                    self::$completelyFailed++;
                    self::$errorLog .= "Error in " . $brandName . ". The name field is empty.\n";
                    continue;
                }

                if ($brandObj instanceof Brand) {
                    self::mapData($brandName, $brand, $countryCode, $brandObj);
                    $brandObj->setPublished(false);
                    $brandObj->save();
                } else {
                    $newBrand = new Brand();
                    $newBrand->setKey(\Pimcore\Model\Element\Service::getValidKey($brandName, 'object'));
                    $parentId = Utils::getOrCreateFolderIdByPath("/Brands", 1);
                    $newBrand->setParentId($parentId);
                    self::mapData($brandName, $brand, $countryCode, $newBrand);
                    $newBrand->save();
                }
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
        }

        Utils::logSummary(
            "Brands Import Summary.txt",
            "/Logs/Brands/Brands Import Summary.txt",
            "/Logs/Brands",
            self::$totalObjects,
            self::$partialFailed,
            self::$completelyFailed,
            self::$fullySuccessful
        );

        Utils::logError(
            "Brands Error Report.txt",
            "/Logs/Brands/Brands Error Report.txt",
            "/Logs/Brands",
            self::$errorLog
        );
    }
}
