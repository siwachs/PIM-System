<?php

namespace App\Utils;

use Pimcore\Model\DataObject\Brand;
use Pimcore\Model\DataObject\OperatingSystem;

class OperatingSystemStorageMethods
{
    // Properties for tracking import status
    private static $totalObjects = 0;
    private static $partialFailed = 0;
    private static $completelyFailed = 0;
    private static $fullySuccessful = 0;
    private static $errorLog = "";

    // Constants for Operating Systems log summary
    const OPERATING_SYSTEMS_ASSET_FILENAME = "Operating Systems Import Summary.txt";
    const OPERATING_SYSTEMS_ASSET_FILE_PATH = "/Logs/OperatingSystems/Operating Systems Import Summary.txt";
    const OPERATING_SYSTEMS_PARENT_DIRECTORY_PATH = "/Logs/OperatingSystems";

    // Constants for Operating Systems error log
    const OPERATING_SYSTEMS_ERROR_ASSET_FILENAME = "Operating Systems Error Report.txt";
    const OPERATING_SYSTEMS_ERROR_ASSET_FILE_PATH = "/Logs/OperatingSystems/Operating Systems Error Report.txt";
    const OPERATING_SYSTEMS_ERROR_PARENT_DIRECTORY_PATH = "/Logs/OperatingSystems";

    private static function mapData($osName, $os, $countryCode, $osObj)
    {
        $osObj->setName($os['Name'], $countryCode);
        $osObj->setDescription($os['Description'], $countryCode);
        $osObj->setUpdatesPatching($os['Updates/Patching'], $countryCode);
        $osObj->setSupportAndMaintenance($os['Support & Maintenance'], $countryCode);
        $osObj->setVersion($os['Version']);
        $osObj->setLicenseType($os['License Type']);
        $osObj->setCompatablity($os['Compatibility']);
        $osObj->setSystemReqirements($os['System Requirements']);
        $osObj->setSecurityFeatures($os['Security Features']);
        $osObj->setUi($os['UI']);
        $osObj->setBit($os['Bit']);

        $brand = Utils::getObjectByPathIfExists(Brand::class, '/Brands/' . $os['Brand']);
        if ($brand == null) {
            self::$partialFailed++;
            self::$errorLog .= "Warning: The brand object of " .
                $os['Brand'] . " is missing in " . $osName . ".\n";
        } else {
            $osObj->setBrand([$brand]);
            self::$fullySuccessful++;
        }

        $osObj->setQuickStartGuide(Utils::getSocialMediaLinkObject(
            $os['Quick Start Guide Link'],
            $os['Quick Start Guide Link Text'],
            $os['Quick Start Guide Title']
        ));
    }

    /**
     * Store Operating Systems
     *
     * @param array $osArray An array containing operating system data
     * @param string $countryCode The country code
     */
    public static function storeOperatingSystems($osArray, $countryCode)
    {
        self::$totalObjects = count($osArray);

        foreach ($osArray as $os) {
            try {
                $osName = $os['Object Name'];
                if (empty($os['Name'])) {
                    self::$completelyFailed++;
                    self::$errorLog .= "Error: The name field is empty in " . $osName . ".\n";
                    continue;
                }

                $osObj = self::fetchOperatingSystem($osName);

                if ($osObj instanceof OperatingSystem) {
                    self::updateOperatingSystem($osName, $os, $countryCode, $osObj);
                } else {
                    self::createOperatingSystem($osName, $os, $countryCode);
                }
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
        }

        // Log import summary and error report
        self::logOperatingSystemSummary();
    }

    // ...

    /**
     * Fetch an operating system based on the provided name
     *
     * @param string $osName Operating system name
     * @return OperatingSystem|null Returns an OperatingSystem object or null if not found
     */
    private static function fetchOperatingSystem($osName)
    {
        return OperatingSystem::getByPath('/Operating Systems/' . $osName);
    }

    /**
     * Update an existing operating system
     *
     * @param string $osName Operating system name
     * @param array $os Operating system data
     * @param string $countryCode Country code for the operating system
     * @param OperatingSystem $osObj Existing OperatingSystem object
     */
    private static function updateOperatingSystem($osName, $os, $countryCode, $osObj)
    {
        self::mapData($osName, $os, $countryCode, $osObj);
        $osObj->setPublished(false);
        $osObj->save();
    }

    /**
     * Create a new operating system
     *
     * @param string $osName Operating system name
     * @param array $os Operating system data
     * @param string $countryCode Country code for the operating system
     */
    private static function createOperatingSystem($osName, $os, $countryCode)
    {
        $newOS = new OperatingSystem();
        $newOS->setKey(\Pimcore\Model\Element\Service::getValidKey($osName, 'object'));
        $parentId = Utils::getOrCreateFolderIdByPath("/Operating Systems", 1);
        $newOS->setParentId($parentId);
        self::mapData($osName, $os, $countryCode, $newOS);
        $newOS->save();
    }

    /**
     * Log the operating system import summary
     */
    private static function logOperatingSystemSummary()
    {
        // Log import summary and error report
        Utils::logSummary(
            self::OPERATING_SYSTEMS_ASSET_FILENAME,
            self::OPERATING_SYSTEMS_ASSET_FILE_PATH,
            self::OPERATING_SYSTEMS_PARENT_DIRECTORY_PATH,
            self::$totalObjects,
            self::$partialFailed,
            self::$completelyFailed,
            self::$fullySuccessful
        );

        Utils::logError(
            self::OPERATING_SYSTEMS_ERROR_ASSET_FILENAME,
            self::OPERATING_SYSTEMS_ERROR_ASSET_FILE_PATH,
            self::OPERATING_SYSTEMS_ERROR_PARENT_DIRECTORY_PATH,
            self::$errorLog
        );
    }
}
