<?php

namespace App\Utils;

use Pimcore\Model\DataObject\RAM;

class RAMStorageMethods
{
    private static $totalObjects = 0;
    private static $partialFailed = 0;
    private static $completelyFailed = 0;
    private static $fullySuccessful = 0;
    private static $errorLog = "";

    private static function mapData($ramName, $ram, $countryCode, $ramObj)
    {
        $fullySuccessful = true;
        $ramObj->setName($ram['Name'], $countryCode);
        $ramObj->setDescription($ram['Description'], $countryCode);
        $ramObj->setRAMType($ram['RAM Type']);
        $ramObj->setCapacity($ram['Capacity']);
        $ramObj->setSpeed($ram['Speed']);

        $brand = Utils::getBrandIfExists('/Brands/' . $ram['Brand']);
        if ($brand == null) {
            self::$partialFailed++;
            self::$errorLog .= "Warning in the brand name: in " .
                $ramName . " the brand object of " .
                $ram['Brand'] . " is missing.\n";
            $fullySuccessful = false;
        } else {
            $ramObj->setBrand([$brand]);
        }

        $manufacturer = Utils::getManufacturerIfExists('/Manufacturers/' . $ram['Manufacturer']);
        if ($manufacturer === null) {
            self::$partialFailed++;
            self::$errorLog .= "Warning in the manufacturer name: in " .
                $ramName . " the manufacturer object of " .
                $ram['Manufacturer'] . " is missing.\n";
            $fullySuccessful = false;
        } else {
            $ramObj->setManufacturer([$manufacturer]);
        }

        if ($fullySuccessful) {
            self::$fullySuccessful++;
        }
    }

    /**
     * Store RAM
     *
     * @param array $ramArray An array containing RAM data
     * @param string $countryCode The country code
     */
    public static function storeRAM($ramArray, $countryCode)
    {
        self::$totalObjects = count($ramArray);
        foreach ($ramArray as $ram) {
            try {
                $ramName = $ram['Object Name'];
                $ramObj = RAM::getByPath('/RAM/' . $ramName);
                if (empty($ram['Name'])) {
                    self::$completelyFailed++;
                    self::$errorLog .= "Error in " . $ramName . ". The name field is empty.\n";
                    continue;
                }

                if ($ramObj instanceof RAM) {
                    self::mapData($ramName, $ram, $countryCode, $ramObj);
                    $ramObj->setPublished(false);
                    $ramObj->save();
                } else {
                    $newRAM = new RAM();
                    $newRAM->setKey(\Pimcore\Model\Element\Service::getValidKey($ramName, 'object'));
                    $parentId = Utils::getOrCreateFolderIdByPath("/RAM", 1);
                    $newRAM->setParentId($parentId);
                    self::mapData($ramName, $ram, $countryCode, $newRAM);
                    $newRAM->save();
                }
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
        }

        Utils::logSummary(
            "RAM Import Summary.txt",
            "/Logs/RAMs/RAM Import Summary.txt",
            "/Logs/RAMs",
            self::$totalObjects,
            self::$partialFailed,
            self::$completelyFailed,
            self::$fullySuccessful
        );

        Utils::logError(
            "RAM Error Report.txt",
            "/Logs/RAMs/RAM Error Report.txt",
            "/Logs/RAMs",
            self::$errorLog
        );
    }
}
