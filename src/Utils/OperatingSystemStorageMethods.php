<?php

namespace App\Utils;

use Pimcore\Model\DataObject\OperatingSystem;

class OperatingSystemStorageMethods
{
    private static $totalObjects = 0;
    private static $partialFailed = 0;
    private static $completelyFailed = 0;
    private static $fullySuccessful = 0;
    private static $errorLog = "";

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

        $brand = Utils::getBrandIfExists('/Brands/' . $os['Brand']);
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
                $osObj = OperatingSystem::getByPath('/Operating Systems/' . $osName);
                if (empty($os['Name'])) {
                    self::$completelyFailed++;
                    self::$errorLog .= "Error: The name field is empty in " . $osName . ".\n";
                    continue;
                }

                if ($osObj instanceof OperatingSystem) {
                    self::mapData($osName, $os, $countryCode, $osObj);
                    $osObj->setPublished(false);
                    $osObj->save();
                } else {
                    $newOS = new OperatingSystem();
                    $newOS->setKey(\Pimcore\Model\Element\Service::getValidKey($osName, 'object'));
                    $parentId = Utils::getOrCreateFolderIdByPath("/Operating Systems", 1);
                    $newOS->setParentId($parentId);
                    self::mapData($osName, $os, $countryCode, $newOS);
                    $newOS->save();
                }
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
        }

        Utils::logSummary(
            "Operating Systems Import Summary.txt",
            "/Logs/OperatingSystems/Operating Systems Import Summary.txt",
            "/Logs/OperatingSystems",
            self::$totalObjects,
            self::$partialFailed,
            self::$completelyFailed,
            self::$fullySuccessful
        );

        Utils::logError(
            "Operating Systems Error Report.txt",
            "/Logs/OperatingSystems/Operating Systems Error Report.txt",
            "/Logs/OperatingSystems",
            self::$errorLog
        );
    }
}
