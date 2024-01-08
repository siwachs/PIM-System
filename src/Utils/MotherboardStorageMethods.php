<?php

namespace App\Utils;

use Pimcore\Model\DataObject\MotherBoard;
use Pimcore\Model\DataObject\Brand;
use Pimcore\Model\DataObject\Manufacturer;

class MotherboardStorageMethods
{
    // Properties for tracking import status
    private static $totalObjects = 0;
    private static $partialFailed = 0;
    private static $completelyFailed = 0;
    private static $fullySuccessful = 0;
    private static $errorLog = "";

    // Constants for Motherboards log summary
    const MOTHERBOARDS_ASSET_FILENAME = "Motherboards Import Summary.txt";
    const MOTHERBOARDS_ASSET_FILE_PATH = "/Logs/Motherboards/Motherboards Import Summary.txt";
    const MOTHERBOARDS_PARENT_DIRECTORY_PATH = "/Logs/Motherboards";

    // Constants for Motherboards error log
    const MOTHERBOARDS_ERROR_ASSET_FILENAME = "Motherboards Error Report.txt";
    const MOTHERBOARDS_ERROR_ASSET_FILE_PATH = "/Logs/Motherboards/Motherboards Error Report.txt";
    const MOTHERBOARDS_ERROR_PARENT_DIRECTORY_PATH = "/Logs/Motherboards";

    private static function mapData($motherboardName, $motherboard, $countryCode, $motherboardObj)
    {
        $fullySuccessful = true;
        $motherboardObj->setModelNumber($motherboard['Model Number']);
        $motherboardObj->setName($motherboard['Name'], $countryCode);
        $motherboardObj->setDescription($motherboard['Description'], $countryCode);
        $motherboardObj->setFeatures($motherboard['Features'], $countryCode);
        $motherboardObj->setFormFactor($motherboard['Form Factor']);
        $motherboardObj->setPowerConnectors($motherboard['Power Connectors']);
        $motherboardObj->setStorageInterfaces($motherboard['Storage Interfaces']);
        $motherboardObj->setConnectivityPorts($motherboard['Connectivity Ports']);
        $motherboardObj->setMemorySlots($motherboard['Memory Slots']);
        $motherboardObj->setSocketType($motherboard['Socket Type']);
        $motherboardObj->setFirmware($motherboard['Firmware']);
        $motherboardObj->setQuickStartGuide(Utils::getSocialMediaLinkObject(
            $motherboard['Quick Start Guide Link'],
            $motherboard['Quick Start Guide Link Text'],
            $motherboard['Quick Start Guide Title']
        ));

        $brand = Utils::getObjectByPathIfExists(Brand::class, '/Brands/' . $motherboard['Brand']);
        if ($brand == null) {
            self::$errorLog .= "Warning in the brand name: in " .
                $motherboardName . " the brand object of " .
                $motherboard['Brand'] . " is missing.\n";
            $fullySuccessful = false;
        } else {
            $motherboardObj->setBrand([$brand]);
        }

        $manufacturer = Utils::getObjectByPathIfExists(
            Manufacturer::class,
            '/Manufacturers/' . $motherboard['Manufacturer']
        );
        if ($manufacturer == null) {
            self::$errorLog .= "Warning in the manufacturer name: in " .
                $motherboardName . " the manufacturer object of " .
                $motherboard['Manufacturer'] . " is missing.\n";
            $fullySuccessful = false;
        } else {
            $motherboardObj->setManufacturer([$manufacturer]);
        }

        if ($fullySuccessful) {
            self::$fullySuccessful++;
        } else {
            self::$partialFailed++;
        }
    }

    /**
     * Store Motherboards
     *
     * @param array $motherboardArray An array containing motherboard data
     * @param string $countryCode The country code
     */
    public static function storeMotherboards($motherboardArray, $countryCode)
    {
        self::$totalObjects = count($motherboardArray);

        foreach ($motherboardArray as $motherboard) {
            try {
                $motherboardName = $motherboard['Object Name'];
                if (empty($motherboard['Name'])) {
                    self::$completelyFailed++;
                    self::$errorLog .= "Error in " . $motherboardName . ". The name field is empty.\n";
                    continue;
                }

                $motherboardObj = self::fetchMotherboard($motherboardName);

                if ($motherboardObj instanceof Motherboard) {
                    self::updateMotherboard($motherboardName, $motherboard, $countryCode, $motherboardObj);
                } else {
                    self::createMotherboard($motherboardName, $motherboard, $countryCode);
                }
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
        }

        // Log import summary and error report
        self::logMotherboardSummary();
    }

    // ...

    /**
     * Fetch a motherboard based on provided name
     *
     * @param string $motherboardName Motherboard name
     * @return Motherboard|null Returns a Motherboard object or null if not found
     */
    private static function fetchMotherboard($motherboardName)
    {
        return Motherboard::getByPath('/Motherboards/' . $motherboardName);
    }

    /**
     * Update an existing motherboard
     *
     * @param string $motherboardName Motherboard name
     * @param array $motherboard Motherboard data
     * @param string $countryCode Country code for motherboard
     * @param Motherboard $motherboardObj Existing Motherboard object
     */
    private static function updateMotherboard($motherboardName, $motherboard, $countryCode, $motherboardObj)
    {
        self::mapData($motherboardName, $motherboard, $countryCode, $motherboardObj);
        $motherboardObj->setPublished(false);
        $motherboardObj->save();
    }

    /**
     * Create a new motherboard
     *
     * @param string $motherboardName Motherboard name
     * @param array $motherboard Motherboard data
     * @param string $countryCode Country code for motherboard
     */
    private static function createMotherboard($motherboardName, $motherboard, $countryCode)
    {
        $newMotherboard = new Motherboard();
        $newMotherboard->setKey(\Pimcore\Model\Element\Service::getValidKey($motherboardName, 'object'));
        $parentId = Utils::getOrCreateFolderIdByPath("/Motherboards", 1);
        $newMotherboard->setParentId($parentId);
        self::mapData($motherboardName, $motherboard, $countryCode, $newMotherboard);
        $newMotherboard->save();
    }

    /**
     * Log the motherboard import summary
     */
    private static function logMotherboardSummary()
    {
        // Log import summary and error report
        Utils::logSummary(
            self::MOTHERBOARDS_ASSET_FILENAME,
            self::MOTHERBOARDS_ASSET_FILE_PATH,
            self::MOTHERBOARDS_PARENT_DIRECTORY_PATH,
            self::$totalObjects,
            self::$partialFailed,
            self::$completelyFailed,
            self::$fullySuccessful
        );

        Utils::logError(
            self::MOTHERBOARDS_ERROR_ASSET_FILENAME,
            self::MOTHERBOARDS_ERROR_ASSET_FILE_PATH,
            self::MOTHERBOARDS_ERROR_PARENT_DIRECTORY_PATH,
            self::$errorLog
        );
    }
}
