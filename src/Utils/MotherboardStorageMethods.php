<?php

namespace App\Utils;

use Pimcore\Model\DataObject\MotherBoard;

class MotherboardStorageMethods
{
    private static $totalObjects = 0;
    private static $partialFailed = 0;
    private static $completelyFailed = 0;
    private static $fullySuccessful = 0;
    private static $errorLog = "";

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

        $brand = Utils::getBrandIfExists('/Brands/' . $motherboard['Brand']);
        if ($brand == null) {
            self::$partialFailed++;
            self::$errorLog .= "Warning in the brand name: in " .
                $motherboardName . " the brand object of " .
                $motherboard['Brand'] . " is missing.\n";
            $fullySuccessful = false;
        } else {
            $motherboardObj->setBrand([$brand]);
        }

        $manufacturer = Utils::getManufacturerIfExists('/Manufacturers/' . $motherboard['Manufacturer']);
        if ($manufacturer == null) {
            self::$partialFailed++;
            self::$errorLog .= "Warning in the manufacturer name: in " .
                $motherboardName . " the manufacturer object of " .
                $motherboard['Manufacturer'] . " is missing.\n";
            $fullySuccessful = false;
        } else {
            $motherboardObj->setManufacturer([$manufacturer]);
        }

        if ($fullySuccessful) {
            self::$fullySuccessful++;
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
                $motherboardObj = Motherboard::getByPath('/Motherboards/' . $motherboardName);
                if (empty($motherboard['Name'])) {
                    self::$completelyFailed++;
                    self::$errorLog .= "Error in " . $motherboardName . ". The name field is empty.\n";
                    continue;
                }

                if ($motherboardObj instanceof Motherboard) {
                    self::mapData($motherboardName, $motherboard, $countryCode, $motherboardObj);
                    $motherboardObj->setPublished(false);
                    $motherboardObj->save();
                } else {
                    $newMotherboard = new Motherboard();
                    $newMotherboard->setKey(\Pimcore\Model\Element\Service::getValidKey($motherboardName, 'object'));
                    $parentId = Utils::getOrCreateFolderIdByPath("/Motherboards", 1);
                    $newMotherboard->setParentId($parentId);
                    self::mapData($motherboardName, $motherboard, $countryCode, $newMotherboard);
                    $newMotherboard->save();
                }
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
        }

        Utils::logSummary(
            "Motherboards Import Summary.txt",
            "/Logs/Motherboards/Motherboards Import Summary.txt",
            "/Logs/Motherboards",
            self::$totalObjects,
            self::$partialFailed,
            self::$completelyFailed,
            self::$fullySuccessful
        );

        Utils::logError(
            "Motherboards Error Report.txt",
            "/Logs/Motherboards/Motherboards Error Report.txt",
            "/Logs/Motherboards",
            self::$errorLog
        );
    }
}
