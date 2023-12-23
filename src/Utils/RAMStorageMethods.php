<?php

namespace App\Utils;

use Pimcore\Model\DataObject\RAM;

class RAMStorageMethods
{
    // Properties for tracking import status
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
            self::$errorLog .= "Warning in the brand name: in " .
                $ramName . " the brand object of " .
                $ram['Brand'] . " is missing.\n";
            $fullySuccessful = false;
        } else {
            $ramObj->setBrand([$brand]);
        }

        $manufacturer = Utils::getManufacturerIfExists('/Manufacturers/' . $ram['Manufacturer']);
        if ($manufacturer === null) {
            self::$errorLog .= "Warning in the manufacturer name: in " .
                $ramName . " the manufacturer object of " .
                $ram['Manufacturer'] . " is missing.\n";
            $fullySuccessful = false;
        } else {
            $ramObj->setManufacturer([$manufacturer]);
        }

        if ($fullySuccessful) {
            self::$fullySuccessful++;
        } else {
            self::$partialFailed++;
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
                if (empty($ram['Name'])) {
                    self::$completelyFailed++;
                    self::$errorLog .= "Error in " . $ramName . ". The name field is empty.\n";
                    continue;
                }

                $ramObj = self::fetchRAM($ramName);

                if ($ramObj instanceof RAM) {
                    self::updateRAM($ramName, $ram, $countryCode, $ramObj);
                } else {
                    self::createRAM($ramName, $ram, $countryCode);
                }
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
        }

        // Log import summary and error report
        self::logRAMSummary();
    }

    /**
     * Fetch a RAM based on the provided name
     *
     * @param string $ramName RAM name
     * @return RAM|null Returns a RAM object or null if not found
     */
    private static function fetchRAM($ramName)
    {
        return RAM::getByPath('/RAMs/' . $ramName);
    }

    /**
     * Update an existing RAM
     *
     * @param string $ramName RAM name
     * @param array $ram RAM data
     * @param string $countryCode Country code for the RAM
     * @param RAM $ramObj Existing RAM object
     */
    private static function updateRAM($ramName, $ram, $countryCode, $ramObj)
    {
        self::mapData($ramName, $ram, $countryCode, $ramObj);
        $ramObj->setPublished(false);
        $ramObj->save();
    }

    /**
     * Create a new RAM
     *
     * @param string $ramName RAM name
     * @param array $ram RAM data
     * @param string $countryCode Country code for the RAM
     */
    private static function createRAM($ramName, $ram, $countryCode)
    {
        $newRAM = new RAM();
        $newRAM->setKey(\Pimcore\Model\Element\Service::getValidKey($ramName, 'object'));
        $parentId = Utils::getOrCreateFolderIdByPath("/RAM", 1);
        $newRAM->setParentId($parentId);
        self::mapData($ramName, $ram, $countryCode, $newRAM);
        $newRAM->save();
    }

    /**
     * Log the RAM import summary
     */
    private static function logRAMSummary()
    {
        // Log import summary and error report
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
