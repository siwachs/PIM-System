<?php

namespace App\Utils;

use Pimcore\Model\DataObject\Manufacturer;
use Pimcore\Model\DataObject\SSD;

class SSDStorageMethods
{
    // Properties for tracking import status
    private static $totalObjects = 0;
    private static $partialFailed = 0;
    private static $completelyFailed = 0;
    private static $fullySuccessful = 0;
    private static $errorLog = "";

    // Constants for SSDs log summary
    const SSDS_ASSET_FILENAME = "SSDs Import Summary.txt";
    const SSDS_ASSET_FILE_PATH = "/Logs/SSDs/SSDs Import Summary.txt";
    const SSDS_PARENT_DIRECTORY_PATH = "/Logs/SSDs";

    // Constants for SSDs error log
    const SSDS_ERROR_ASSET_FILENAME = "SSDs Error Report.txt";
    const SSDS_ERROR_ASSET_FILE_PATH = "/Logs/SSDs/SSDs Error Report.txt";
    const SSDS_ERROR_PARENT_DIRECTORY_PATH = "/Logs/SSDs";

    private static function mapData($ssdName, $ssd, $countryCode, $ssdObj)
    {
        $fullySuccessful = true;
        $ssdObj->setName($ssd['Name'], $countryCode);
        $ssdObj->setDescription($ssd['Description'], $countryCode);
        $ssdObj->setSSDType($ssd['SSD Type']);
        $ssdObj->setCapacity($ssd['Capacity']);
        $ssdObj->setInterface($ssd['Interface']);
        $ssdObj->setFormFactor($ssd['Form Factor']);
        $ssdObj->setReadWriteSpeed($ssd['Read/Write Speed']);
        $ssdObj->setEnduranceOrLifespan($ssd['Endurance/Lifespan']);

        $brand = Utils::getObjectByPathIfExists(Manufacturer::class, '/Brands/' . $ssd['Brand']);
        if ($brand == null) {
            self::$errorLog .= "Warning: in the brand name, in " .
                $ssdName . ", the brand object of " .
                $ssd['Brand'] . " is missing.\n";
            $fullySuccessful = false;
        } else {
            $ssdObj->setBrand([$brand]);
        }

        $manufacturer = Utils::getObjectByPathIfExists(Manufacturer::class, '/Manufacturers/' . $ssd['Manufacturer']);
        if ($manufacturer === null) {
            self::$errorLog .= "Warning: in the manufacturer name, in " .
                $ssdName . ", the manufacturer object of " .
                $ssd['Manufacturer'] . " is missing.\n";
            $fullySuccessful = false;
        } else {
            $ssdObj->setManufacturer([$manufacturer]);
        }

        if ($fullySuccessful) {
            self::$fullySuccessful++;
        } else {
            self::$partialFailed++;
        }
    }

    /**
     * Store SSDs
     *
     * @param array $ssdArray An array containing SSD data
     * @param string $countryCode The country code
     */
    public static function storeSSDs($ssdArray, $countryCode)
    {
        self::$totalObjects = count($ssdArray);

        foreach ($ssdArray as $ssd) {
            try {
                $ssdName = $ssd['Object Name'];
                if (empty($ssd['Name'])) {
                    self::$completelyFailed++;
                    self::$errorLog .= "Error: in " . $ssdName . ", the Name field is empty.\n";
                    continue;
                }

                $ssdObj = self::fetchSSD($ssdName);

                if ($ssdObj instanceof SSD) {
                    self::updateSSD($ssdName, $ssd, $countryCode, $ssdObj);
                } else {
                    self::createSSD($ssdName, $ssd, $countryCode);
                }
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
        }

        // Log import summary and error report
        self::logSSDSummary();
    }

    /**
     * Fetch an SSD based on the provided name
     *
     * @param string $ssdName SSD name
     * @return SSD|null Returns an SSD object or null if not found
     */
    private static function fetchSSD($ssdName)
    {
        return SSD::getByPath('/SSDs/' . $ssdName);
    }

    /**
     * Update an existing SSD
     *
     * @param string $ssdName SSD name
     * @param array $ssd SSD data
     * @param string $countryCode Country code for the SSD
     * @param SSD $ssdObj Existing SSD object
     */
    private static function updateSSD($ssdName, $ssd, $countryCode, $ssdObj)
    {
        self::mapData($ssdName, $ssd, $countryCode, $ssdObj);
        $ssdObj->setPublished(false);
        $ssdObj->save();
    }

    /**
     * Create a new SSD
     *
     * @param string $ssdName SSD name
     * @param array $ssd SSD data
     * @param string $countryCode Country code for the SSD
     */
    private static function createSSD($ssdName, $ssd, $countryCode)
    {
        $newSSD = new SSD();
        $newSSD->setKey(\Pimcore\Model\Element\Service::getValidKey($ssdName, 'object'));
        $parentId = Utils::getOrCreateFolderIdByPath("/SSDs", 1);
        $newSSD->setParentId($parentId);
        self::mapData($ssdName, $ssd, $countryCode, $newSSD);
        $newSSD->save();
    }

    /**
     * Log the SSD import summary
     */
    private static function logSSDSummary()
    {
        // Log import summary and error report
        Utils::logSummary(
            self::SSDS_ASSET_FILENAME,
            self::SSDS_ASSET_FILE_PATH,
            self::SSDS_PARENT_DIRECTORY_PATH,
            self::$totalObjects,
            self::$partialFailed,
            self::$completelyFailed,
            self::$fullySuccessful
        );

        Utils::logError(
            self::SSDS_ERROR_ASSET_FILENAME,
            self::SSDS_ERROR_ASSET_FILE_PATH,
            self::SSDS_ERROR_PARENT_DIRECTORY_PATH,
            self::$errorLog
        );
    }
}
