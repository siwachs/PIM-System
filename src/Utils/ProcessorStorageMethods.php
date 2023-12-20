<?php

namespace App\Utils;

use Pimcore\Model\DataObject\Processor;

class ProcessorStorageMethods
{
    private static $totalObjects = 0;
    private static $partialFailed = 0;
    private static $completelyFailed = 0;
    private static $fullySuccessful = 0;
    private static $errorLog = "";

    private static function mapData($processorName, $processor, $countryCode, $processorObj)
    {
        $fullySuccessful = true;
        $processorObj->setModelNumber($processor['Model Number']);
        $processorObj->setName($processor['Name'], $countryCode);
        $processorObj->setDescription($processor['Description'], $countryCode);
        $processorObj->setSocketType($processor['Socket Type']);
        $processorObj->setNumberOfCores($processor['Number of Cores']);
        $processorObj->setManufacturingProcess($processor['Manufacturing Process']);
        $processorObj->setCacheMemory($processor['Cache Memory']);
        $processorObj->setClockSpeed($processor['Clock Speed']);
        $processorObj->setArchitecture($processor['Architecture']);
        $processorObj->setInstructionSet($processor['Instruction Set']);
        $processorObj->setCompatibility($processor['Compatibility']);
        $processorObj->setQuickStartGuide(Utils::getSocialMediaLinkObject(
            $processor['Quick Start Guide Link'],
            $processor['Quick Start Guide Link Text'],
            $processor['Quick Start Guide Title']
        ));

        $brand = Utils::getBrandIfExists('/Brands/' . $processor['Brand']);
        if ($brand == null) {
            self::$partialFailed++;
            self::$errorLog .= "Warning in the brand name: in " .
                $processorName . " the brand object of " .
                $processor['Brand'] . " is missing.\n";
            $fullySuccessful = false;
        } else {
            $processorObj->setBrand([$brand]);
        }

        $manufacturer = Utils::getManufacturerIfExists('/Manufacturers/' . $processor['Manufacturer']);
        if ($manufacturer === null) {
            self::$partialFailed++;
            self::$errorLog .= "Warning in the manufacturer name: in " .
                $processorName . " the manufacturer object of " .
                $processor['Manufacturer'] . " is missing.\n";
            $fullySuccessful = false;
        } else {
            $processorObj->setManufacturer([$manufacturer]);
        }

        if ($fullySuccessful) {
            self::$fullySuccessful++;
        }
    }

    /**
     * Store Processors
     *
     * @param array $processorArray An array containing processor data
     * @param string $countryCode The country code
     */
    public static function storeProcessors($processorArray, $countryCode)
    {
        self::$totalObjects = count($processorArray);
        foreach ($processorArray as $processor) {
            try {
                $processorName = $processor['Object Name'];
                $processorObj = Processor::getByPath('/Processors/' . $processorName);
                if (empty($processor['Name'])) {
                    self::$completelyFailed++;
                    self::$errorLog .= "Error in " . $processorName . ". The name field is empty.\n";
                    continue;
                }

                if ($processorObj instanceof Processor) {
                    self::mapData($processorName, $processor, $countryCode, $processorObj);
                    $processorObj->setPublished(false);
                    $processorObj->save();
                } else {
                    $newProcessor = new Processor();
                    $newProcessor->setKey(\Pimcore\Model\Element\Service::getValidKey($processorName, 'object'));
                    $parentId = Utils::getOrCreateFolderIdByPath("/Processors", 1);
                    $newProcessor->setParentId($parentId);
                    self::mapData($processorName, $processor, $countryCode, $newProcessor);
                    $newProcessor->save();
                }
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
        }

        Utils::logSummary(
            "Processors Import Summary.txt",
            "/Logs/Processors/Processors Import Summary.txt",
            "/Logs/Processors",
            self::$totalObjects,
            self::$partialFailed,
            self::$completelyFailed,
            self::$fullySuccessful
        );

        Utils::logError(
            "Processors Error Report.txt",
            "/Logs/Processors/Processors Error Report.txt",
            "/Logs/Processors",
            self::$errorLog
        );
    }
}
