<?php

namespace App\Utils;

use Pimcore\Model\DataObject\SSD;

class SSDStorageMethods
{
    private static $totalObjects = 0;
    private static $partialFailed = 0;
    private static $completelyFailed = 0;
    private static $fullySuccessful = 0;
    private static $errorLog = "";

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

        $brand = Utils::getBrandIfExists('/Brands/' . $ssd['Brand']);
        if ($brand == null) {
            self::$partialFailed++;
            self::$errorLog .= "Warning: in the brand name, in " .
                $ssdName . ", the brand object of " .
                $ssd['Brand'] . " is missing.\n";
            $fullySuccessful = false;
        } else {
            $ssdObj->setBrand([$brand]);
        }

        $manufacturer = Utils::getManufacturerIfExists('/Manufacturers/' . $ssd['Manufacturer']);
        if ($manufacturer === null) {
            self::$partialFailed++;
            self::$errorLog .= "Warning: in the manufacturer name, in " .
                $ssdName . ", the manufacturer object of " .
                $ssd['Manufacturer'] . " is missing.\n";
            $fullySuccessful = false;
        } else {
            $ssdObj->setManufacturer([$manufacturer]);
        }

        if ($fullySuccessful) {
            self::$fullySuccessful++;
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
                $ssdObj = SSD::getByPath('/SSDs/' . $ssdName);
                if (empty($ssd['Name'])) {
                    self::$completelyFailed++;
                    self::$errorLog .= "Error: in " . $ssdName . ", the Name field is empty.\n";
                    continue;
                }

                if ($ssdObj instanceof SSD) {
                    self::mapData($ssdName, $ssd, $countryCode, $ssdObj);
                    $ssdObj->setPublished(false);
                    $ssdObj->save();
                } else {
                    $newSSD = new SSD();
                    $newSSD->setKey(\Pimcore\Model\Element\Service::getValidKey($ssdName, 'object'));
                    $parentId = Utils::getOrCreateFolderIdByPath("/SSDs", 1);
                    $newSSD->setParentId($parentId);
                    self::mapData($ssdName, $ssd, $countryCode, $newSSD);
                    $newSSD->save();
                }
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
        }

        Utils::logSummary(
            "SSDs Import Summary.txt",
            "/Logs/SSDs/SSDs Import Summary.txt",
            "/Logs/SSDs",
            self::$totalObjects,
            self::$partialFailed,
            self::$completelyFailed,
            self::$fullySuccessful
        );

        Utils::logError(
            "SSDs Error Report.txt",
            "/Logs/SSDs/SSDs Error Report.txt",
            "/Logs/SSDs",
            self::$errorLog
        );
    }
}
