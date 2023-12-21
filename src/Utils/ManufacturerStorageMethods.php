<?php

namespace App\Utils;

use Pimcore\Model\DataObject\Manufacturer;

class ManufacturerStorageMethods
{
    // Properties for tracking import status
    private static $totalObjects = 0;
    private static $partialFailed = 0;
    private static $completelyFailed = 0;
    private static $fullySuccessful = 0;
    private static $errorLog = "";

    private static function mapData($manufacturerName, $manufacturer, $countryCode, $manufacturerObj)
    {
        $manufacturerObj->setLogo(Utils::getAsset('/LOGOS/' . $manufacturer['Logo']));
        $manufacturerObj->setName($manufacturer['Name'], $countryCode);
        $manufacturerObj->setAddress($manufacturer['Address'], $countryCode);
        $manufacturerObj->setContact($manufacturer['Contact'], $countryCode);
        $manufacturerObj->setStandards($manufacturer['Standards']);
        $manufacturerObj->setCountry($manufacturer['Country']);
        $manufacturerObj->setWebsiteLink(Utils::getSocialMediaLinkObject(
            $manufacturer['Website Link'],
            $manufacturer['Website Link Text'],
            $manufacturer['Website Link Title']
        ));
        if (Utils::isValidYearFounded($manufacturer['Year Founded'] ?? 0)) {
            $manufacturerObj->setYearFounded($manufacturer['Year Founded']);
            self::$fullySuccessful++;
        } else {
            self::$partialFailed++;
            self::$errorLog .= "Warning in " . $manufacturerName . " invalid founded year.\n";
        }
    }

    /**
     * Store Manufacturers
     *
     * @param array $manufacturerArray An array containing manufacturer data
     * @param string $countryCode The country code
     */
    public static function storeManufacturers($manufacturerArray, $countryCode)
    {
        self::$totalObjects = count($manufacturerArray);

        foreach ($manufacturerArray as $manufacturer) {
            try {
                $manufacturerName = $manufacturer['Object Name'];
                if (empty($manufacturer['Name'])) {
                    self::$completelyFailed++;
                    self::$errorLog .= "Error in " . $manufacturerName . ". The name field is empty.\n";
                    continue;
                }

                $manufacturerObj = self::fetchManufacturer($manufacturerName);

                if ($manufacturerObj instanceof Manufacturer) {
                    self::updateManufacturer($manufacturerName, $manufacturer, $countryCode, $manufacturerObj);
                } else {
                    self::createManufacturer($manufacturerName, $manufacturer, $countryCode);
                }
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
        }

        // Log import summary and error report
        self::logManufacturerSummary();
    }

    // ...

    /**
     * Fetch a manufacturer based on provided name
     *
     * @param string $manufacturerName Manufacturer name
     * @return Manufacturer|null Returns a Manufacturer object or null if not found
     */
    private static function fetchManufacturer($manufacturerName)
    {
        return Manufacturer::getByPath('/Manufacturers/' . $manufacturerName);
    }

    /**
     * Update an existing manufacturer
     *
     * @param string $manufacturerName Manufacturer name
     * @param array $manufacturer Manufacturer data
     * @param string $countryCode Country code for manufacturer
     * @param Manufacturer $manufacturerObj Existing Manufacturer object
     */
    private static function updateManufacturer($manufacturerName, $manufacturer, $countryCode, $manufacturerObj)
    {
        self::mapData($manufacturerName, $manufacturer, $countryCode, $manufacturerObj);
        $manufacturerObj->setPublished(false);
        $manufacturerObj->save();
    }

    /**
     * Create a new manufacturer
     *
     * @param string $manufacturerName Manufacturer name
     * @param array $manufacturer Manufacturer data
     * @param string $countryCode Country code for manufacturer
     */
    private static function createManufacturer($manufacturerName, $manufacturer, $countryCode)
    {
        $newManufacturer = new Manufacturer();
        $newManufacturer->setKey(\Pimcore\Model\Element\Service::getValidKey($manufacturerName, 'object'));
        $parentId = Utils::getOrCreateFolderIdByPath("/Manufacturers", 1);
        $newManufacturer->setParentId($parentId);
        self::mapData($manufacturerName, $manufacturer, $countryCode, $newManufacturer);
        $newManufacturer->save();
    }

    /**
     * Log the manufacturer import summary
     */
    private static function logManufacturerSummary()
    {
        // Log import summary and error report
        Utils::logSummary(
            "Manufacturers Import Summary.txt",
            "/Logs/Manufacturers/Manufacturers Import Summary.txt",
            "/Logs/Manufacturers",
            self::$totalObjects,
            self::$partialFailed,
            self::$completelyFailed,
            self::$fullySuccessful
        );

        Utils::logError(
            "Manufacturers Error Report.txt",
            "/Logs/Manufacturers/Manufacturers Error Report.txt",
            "/Logs/Manufacturers",
            self::$errorLog
        );
    }
}
