<?php

namespace App\Utils;

use Pimcore\Model\DataObject\SensorsSet;
use Pimcore\Model\DataObject\Fieldcollection;
use Pimcore\Model\DataObject\Fieldcollection\Data\Sensor;

class SensorSetStorageMethods
{
    const OBJECT_NAME = 'Object Name';
    const ERROR_IN = 'Error in ';
    const PRODUCT_CODE = 'Product Code';

    // Properties for tracking import status
    private static $totalObjects = 0;
    private static $partialFailed = 0;
    private static $completelyFailed = 0;
    private static $fullySuccessful = 0;
    private static $errorLog = "";

    /**
     * Map data for sensors
     *
     * @param array $sensor Sensor data
     * @param string $countryCode Country code for sensor
     * @param Sensor $sensorObj Existing Sensor object
     */
    private static function mapData($sensor, $countryCode, $sensorObj)
    {
        $sensorObj->setName($sensor['Name'], $countryCode);
        $sensorObj->setDescription($sensor['Description'], $countryCode);
    }

    /**
     * Writes values in the provided sensor collection based on sensor data and country code.
     *
     * @param array $sensor Sensor data
     * @param string $countryCode The country code
     * @param Sensor $sensorCollection The Sensor Collection to be updated
     * @return bool Returns true if the operation is fully successful, otherwise false
     */
    private static function writeValueInFieldCollection($sensor, $countryCode, $sensorCollection): bool
    {
        $fullySuccessful = true;

        // Set values in the Sensor Collection
        $sensorCollection->setProductCode($sensor[self::PRODUCT_CODE]);
        $sensorCollection->setName($sensor['Name'], $countryCode);
        $sensorCollection->setDescription($sensor['Description'], $countryCode);
        $sensorCollection->setSensorType($sensor['Sensor Type']);

        // Retrieve Brand and set if available
        $brand = Utils::getBrandIfExists('/Brands/' . $sensor['Brand']);
        if ($brand !== null) {
            $sensorCollection->setBrand([$brand]);
        } else {
            // Log if brand is missing
            $fullySuccessful = false;
            self::$errorLog .= "Warning in the brand name: in " .
                $sensor['Name'] . " the brand object of " .
                $sensor['Brand'] . " is missing.\n";
        }

        // Retrieve Manufacturer and set if available
        $manufacturer = Utils::getManufacturerIfExists('/Manufacturers/' . $sensor['Manufacturer']);
        if ($manufacturer == null) {
            // Log if manufacturer is missing
            self::$errorLog .= "Warning in the manufacturer name: in " .
                $sensor['Name'] . " the manufacturer object of " .
                $sensor['Manufacturer'] . " is missing.";
            $fullySuccessful = false;
        } else {
            $sensorCollection->setManufacturer([$manufacturer]);
        }

        return $fullySuccessful;
    }

    /**
     * Maps field collection with sensor data.
     *
     * @param array $sensor Sensor data
     * @param string $countryCode The country code
     * @param SensorSet $sensorObj The Sensor Set object
     */
    private static function mapFieldCollection($sensor, $countryCode, $sensorObj)
    {
        $fullySuccessful = true;

        // Retrieve existing Field Collection from the SensorSet object
        $fieldCollection = $sensorObj->getSensors();
        if (!$fieldCollection instanceof Fieldcollection) {
            $fieldCollection = new Fieldcollection();
        }

        foreach ($fieldCollection as $field) {
            if ($field instanceof Sensor && $sensor[self::PRODUCT_CODE] === $field->getProductCode()) {
                // Update existing Sensor Collection with new values
                $fullySuccessful = self::writeValueInFieldCollection($sensor, $countryCode, $field);
                if ($fullySuccessful) {
                    self::$fullySuccessful++;
                } else {
                    self::$partialFailed++;
                }
                return;
            }
        }

        // If the Sensor Collection doesn't exist, create a new one
        $sensorCollection = new Sensor();
        $fullySuccessful = self::writeValueInFieldCollection($sensor, $countryCode, $sensorCollection);

        if ($fullySuccessful) {
            self::$fullySuccessful++;
        } else {
            self::$partialFailed++;
        }

        $fieldCollection->add($sensorCollection);
        $sensorObj->setSensors($fieldCollection);
    }



    /**
     * Store Sensor Set data from an array.
     *
     * @param array $sensorSetArray An array containing sensors and sensor set data
     * @param string $countryCode The country code for the sensor set
     */
    public static function storeSensorSet($sensorSetArray, $countryCode)
    {
        // Initialize total objects count
        self::$totalObjects = count($sensorSetArray);

        // Sort the provided data based on 'Object Name' field
        usort($sensorSetArray, function ($a, $b) {
            return ($a[self::OBJECT_NAME] !== '' && $b[self::OBJECT_NAME] === '') ? -1 : 1;
        });

        // Process each sensor set in the array
        foreach ($sensorSetArray as $sensorset) {
            try {
                self::processSensorSet($sensorset, $countryCode);
            } catch (\Exception $e) {
                // Handle exceptions if any occur during processing
                dump($e->getMessage());
            }
        }

        // Log import summary and error report
        self::logSensorSetSummary();
    }

    /**
     * Process each Sensor Set from the provided array.
     * This function handles creation, update, and logging for each Sensor Set.
     *
     * @param array $sensorset The sensor set data
     * @param string $countryCode The country code for the sensor set
     */
    private static function processSensorSet($sensorset, $countryCode)
    {
        $sensorsetName = $sensorset['Object Name'];
        $productCode = $sensorset['Product Code'];
        $sensorName = $sensorset['Name'];
        $objectType = $sensorset['Object Type'];

        // Check if the 'Object Name' field is empty for a set
        if (empty($sensorsetName) && $objectType === 'Set') {
            self::$completelyFailed++;
            self::$errorLog .= self::ERROR_IN . $sensorsetName . ". The name field is empty.\n";
            return;
        }

        // Check if the 'Name' or 'Product Code' field is empty or invalid for a sensor
        if (
            empty($sensorName)
            && $objectType === 'Sensor'
            && !preg_match('/^SET\d+-SNSR\d+$/', $productCode)
        ) {
            self::$completelyFailed++;
            self::$errorLog .= self::ERROR_IN . $sensorName . ". The name or product code field is empty or invalid.\n";
            return;
        }

        // Fetch the Sensor Set object
        $sensorSetObj = self::fetchSensorSet($sensorsetName);

        // Process Sensor Set or Sensor based on the object type
        if ($objectType === 'Set') {
            self::processSensorSetTypeSet($sensorSetObj, $sensorsetName, $sensorset, $countryCode);
        } elseif ($objectType === 'Sensor') {
            self::processSensorSetTypeSensor($sensorSetObj, $sensorsetName, $sensorset, $countryCode);
        }
    }

    /**
     * Process Sensor Set type data.
     *
     * @param SensorsSet|null $sensorSetObj The Sensor Set object
     * @param string $sensorsetName The sensor set name
     * @param array $sensorset The sensor set data
     * @param string $countryCode The country code for the sensor set
     */
    private static function processSensorSetTypeSet($sensorSetObj, $sensorsetName, $sensorset, $countryCode)
    {
        if ($sensorSetObj instanceof SensorsSet) {
            self::updateSensorSet($sensorset, $countryCode, $sensorSetObj);
        } else {
            self::createSensorSet($sensorsetName, $sensorset, $countryCode);
        }
    }

    /**
     * Process Sensor type data.
     *
     * @param SensorsSet|null $sensorSetObj The Sensor Set object
     * @param string $sensorsetName The sensor set name
     * @param array $sensorset The sensor set data
     * @param string $countryCode The country code for the sensor set
     */
    private static function processSensorSetTypeSensor($sensorSetObj, $sensorsetName, $sensorset, $countryCode)
    {
        if ($sensorSetObj instanceof SensorsSet) {
            self::mapFieldCollection($sensorset, $countryCode, $sensorSetObj);
            $sensorSetObj->save();
        } else {
            self::$completelyFailed++;
            self::$errorLog .= self::ERROR_IN . $sensorsetName . ". The object for sensor does not exist.\n";
        }
    }


    /**
     * Fetch a sensor set based on provided sensor set name
     *
     * @param string $brandName Brand name
     * @return SensorsSet|null Returns a SensorsSet object or null if not found
     */
    private static function fetchSensorSet($sensorSetName)
    {
        return SensorsSet::getByPath('/Sensor Sets/' . $sensorSetName);
    }

    /**
     * Update an existing sensor set
     *
     * @param array $sensorset Sensor set data
     * @param string $countryCode Country code for sensor set
     * @param SensorSet $sensorSetObj Existing SensorSet object
     */
    private static function updateSensorSet($sensorset, $countryCode, $sensorSetObj)
    {
        self::mapData($sensorset, $countryCode, $sensorSetObj);
        $sensorSetObj->setPublished(false);
        $sensorSetObj->save();
    }


    /**
     * Create a new sensor set
     *
     * @param string $sensorsetName Sensor set name
     * @param array $sensorset Sensor set data
     * @param string $countryCode Country code for sensor set
     */
    private static function createSensorSet($sensorsetName, $sensorset, $countryCode)
    {
        $newSensorSet = new SensorsSet();
        $newSensorSet->setKey(\Pimcore\Model\Element\Service::getValidKey($sensorsetName, 'object'));
        $parentId = Utils::getOrCreateFolderIdByPath("/Sensor Sets", 1);
        $newSensorSet->setParentId($parentId);
        self::mapData($sensorset, $countryCode, $newSensorSet);
        $newSensorSet->save();
    }


    /**
     * Log the SensorSet import summary
     */
    private static function logSensorSetSummary()
    {
        // Log import summary and error report
        Utils::logSummary(
            "SensorSet Import Summary.txt",
            "/Logs/SensorSets/SensorSet Import Summary.txt",
            "/Logs/SensorSets",
            self::$totalObjects,
            self::$partialFailed,
            self::$completelyFailed,
            self::$fullySuccessful
        );

        Utils::logError(
            "SensorSet Error Report.txt",
            "/Logs/SensorSets/SensorSet Error Report.txt",
            "/Logs/SensorSets",
            self::$errorLog
        );
    }
}
