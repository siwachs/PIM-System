<?php

namespace App\Utils;

use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Brand;
use Pimcore\Model\DataObject\Camera;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Data\Video;
use Pimcore\Model\DataObject\HDD;
use Pimcore\Model\DataObject\Manufacturer;
use Pimcore\Model\DataObject\MotherBoard;
use Pimcore\Model\DataObject\OperatingSystem;
use Pimcore\Model\DataObject\Processor;
use Pimcore\Model\DataObject\RAM;
use Pimcore\Model\DataObject\ROM;
use Pimcore\Model\DataObject\Screen;
use Pimcore\Model\DataObject\SensorsSet;
use Pimcore\Model\DataObject\Speakers;
use Pimcore\Model\DataObject\SSD;

class ProductStorageMethodsHelpers
{
    // Properties for tracking import status
    public static $totalObjects = 0;
    public static $partialFailed = 0;
    public static $completelyFailed = 0;
    public static $fullySuccessful = 0;
    public static $errorLog = "";

    public static function handleEmptyTypeVariant($productData)
    {
        $type = $productData['Type'];
        $productName = $productData['Name'];
        $sku = $productData['SKU'];

        if ($type === 'Variant' && (empty($productName) || !preg_match('/^SKU\d+$/', $sku))) {
            self::$completelyFailed++;
            self::$errorLog .= "Error in " . $productName . ". The name or SKU field is empty or invalid.\n";
            return true;
        }

        return false;
    }

    public static function handleNonVariantEmptyFields($productData)
    {
        $type = $productData['Type'];
        $objectName = $productData['Object Name'];
        $productName = $productData['Name'];

        if ($type !== 'Variant' && (empty($objectName) || empty($productName))) {
            self::$completelyFailed++;
            self::$errorLog .= "Error in object" . $objectName .
                ". The name or object name field is empty or invalid.\n";
            return true;
        }

        return false;
    }

    public static function compareProductData($a, $b)
    {
        $isEmptyParentA = empty($a['Type']);
        $isEmptyParentB = empty($b['Type']);

        if ($isEmptyParentA !== $isEmptyParentB) {
            return $isEmptyParentA ? -1 : 1;
        }

        return 0;
    }
}

class ProductStorageMethods extends ProductStorageMethodsHelpers
{
    const PRODUCTS_PATH = "/Products/";
    const PROCESSORS_PATH = '/Processors/';
    const CAMERAS_PATH = '/Cameras/';
    const MOTHERBOARDS_PATH = '/Motherboards/';
    const OPERATING_SYSTEMS_PATH = '/Operating Systems/';
    const RAMS_PATH = '/RAMs/';
    const ROMS_PATH = '/ROMs/';
    const SCREENS_PATH = '/Screens/';
    const SENSOR_SETS_PATH = '/Sensor Sets/';
    const SPEAKERS_PATH = '/Speakers/';
    const SSD_PATH = '/SSDs/';
    const HDD_PATH = '/HDDs/';

    const IS_MISSING = " is missing.\n";
    const CAMERA = "Camera";
    const OPERATING_SYSTEM = "Operating System";
    const MOTHERBOARD = "Motherboard";
    const PROCESSOR = "Processor";
    const RAM = "RAM";
    const ROM = 'ROM';
    const SCREEN = 'Screen';
    const SPEAKERS = 'Speakers';
    const SSD = 'SSD';
    const HDD = 'HDD';
    const SENSORS_SET = "Sensor Sets";
    const CONNECTIVITY_TECHNOLGIES = "Connectivity Technolgies";

    // Constants for log summary
    const ASSET_FILENAME = "Products Import Summary.txt";
    const ASSET_FILE_PATH = "/Logs/Products/Products Import Summary.txt";
    const PARENT_DIRECTORY_PATH = "/Logs/Products";

    // Constants for error log
    const ERROR_ASSET_FILENAME = "Products Error Report.txt";
    const ERROR_ASSET_FILE_PATH = "/Logs/Products/Products Error Report.txt";
    const ERROR_PARENT_DIRECTORY_PATH = "/Logs/Products";

    public static function storeProducts($productArray, $countryCode)
    {
        self::$totalObjects = count($productArray);
        usort($productArray, function ($a, $b) {
            return self::compareProductData($a, $b);
        });

        foreach ($productArray as $productData) {
            try {
                if (self::handleEmptyTypeVariant($productData)) {
                    continue;
                }

                if (self::handleNonVariantEmptyFields($productData)) {
                    continue;
                }

                $objectName = $productData['Object Name'];
                $productHierarchy = $productData['Product Hierarchy'];
                $type = $productData['Type'];
                $productName = $productData['Name'];
                $sku = $productData['SKU'];

                $productObj = self::fetchProduct($objectName, $productHierarchy, $type, $sku);

                if ($productObj instanceof Product) {
                    self::updateProduct($type, $objectName, $productName, $productData, $countryCode, $productObj);
                } else {
                    self::createProduct(
                        $objectName,
                        $productHierarchy,
                        $type,
                        $productName,
                        $productData,
                        $countryCode
                    );
                }
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
        }

        self::logProductSummary();
    }

    private static function fetchProduct($objectName, $productHierarchy, $type, $sku)
    {
        $product = new Product\Listing();
        if ($type === 'Variant') {
            $product->setLimit(1);
            $product->setUnpublished(true);
            $product->filterBySku($sku);
            $filtredProduct = current(iterator_to_array($product));
            return $filtredProduct instanceof Product ? $filtredProduct : null;
        } else {
            $objectPath = self::PRODUCTS_PATH . $productHierarchy . '/' . $objectName;
            $product = Product::getByPath($objectPath);
            return $product instanceof Product ? $product : null;
        }
    }

    private static function updateProduct(
        string $type,
        string $objectName,
        string $productName,
        array $productData,
        string $countryCode,
        Product $productObj
    ) {
        try {
            if ($type === 'Variant') {
                self::mapProductData($type, $productName, $productData, $countryCode, $productObj);
            } else {
                self::mapProductData($type, $objectName, $productData, $countryCode, $productObj);
            }

            $productObj->setPublished(false);
            $productObj->save();
        } catch (\Exception $e) {
            dump($e->getMessage());
        }
    }

    private static function createProduct(
        string $objectName,
        string $productHierarchy,
        string $type,
        string $productName,
        array $productData,
        string $countryCode
    ) {
        try {
            $newProduct = new Product();
            if ($type === 'Variant') {
                $newProduct->setKey(\Pimcore\Model\Element\Service::getValidKey($productName, 'object'));
            } else {
                $newProduct->setKey(\Pimcore\Model\Element\Service::getValidKey($objectName, 'object'));
            }

            if ($type === 'Variant') {
                $parts = explode("/", $productHierarchy);
                $objectName = $parts[count($parts) - 1];

                $rootProduct = Product::getByPath(self::PRODUCTS_PATH . $productHierarchy);

                if (!$rootProduct instanceof Product) {
                    self::$completelyFailed++;
                    self::$errorLog .= 'The object for variant ' . $productName . self::IS_MISSING;
                    return;
                }

                $newProduct->setParent($rootProduct);
                $newProduct->setKey($productName);
                $newProduct->setType(DataObject::OBJECT_TYPE_VARIANT);
            } else {
                $parentId = Utils::getOrCreateFolderIdByPath(self::PRODUCTS_PATH . $productHierarchy, 1);
                $newProduct->setParentId($parentId);
            }

            if ($type === 'Variant') {
                self::mapProductData($type, $productName, $productData, $countryCode, $newProduct);
            } else {
                self::mapProductData($type, $objectName, $productData, $countryCode, $newProduct);
            }

            $newProduct->save();
        } catch (\Exception $e) {
            dump($e->getMessage());
        }
    }

    private static function mapProductData(
        string $type,
        string $productName,
        array $productData,
        string $countryCode,
        Product $productObj
    ) {
        try {
            $fullySuccessful = self::setBaseData($type, $productObj, $productData, $countryCode, $productName);
            self::setAssetData($productObj, $productData, $countryCode);

            if ($type === 'Variant') {
                $fullySuccessful = self::setSalesData($productObj, $productData, $countryCode, $productName);
                $fullySuccessful = self::setPricingData(
                    $productObj,
                    $productData,
                    $countryCode,
                    $productName
                );
            }

            $fullySuccessful = self::setMeasurementsData($productObj, $productData, $productName);
            $fullySuccessful = self::setTechnicalDetailsData($productObj, $productData, $productName);
            self::setAdvanceTechnicalData($productObj, $productData);
            if ($type === 'Variant') {
                self::setClassificationStore($productObj, $productData);
            }

            if ($fullySuccessful) {
                self::$fullySuccessful++;
            } else {
                self::$partialFailed++;
            }
        } catch (\Exception  $e) {
            dump($e->getMessage());
        }
    }

    private static function setBaseData(
        string $type,
        Product $productObj,
        array $productData,
        string $countryCode,
        string $productName
    ): bool {
        try {
            $fullySuccessful = true;
            if ($type === 'Variant') {
                $productObj->setSku($productData['SKU']);
            }

            $productObj->setName($productData['Name'], $countryCode);
            $productObj->setDescription($productData['Description'], $countryCode);
            $productObj->setCountry($productData['Country']);

            $brand = Utils::getObjectByPathIfExists(Brand::class, '/Brands/' . $productData['Brand']);
            if ($brand == null) {
                self::$errorLog .= "Warning in the brand name: in " .
                    $productName . " the brand object of " .
                    $productData['Brand'] . self::IS_MISSING;
                $fullySuccessful = false;
            } else {
                $productObj->setBrand([$brand]);
            }

            $manufacturer = Utils::getObjectByPathIfExists(
                Manufacturer::class,
                '/Manufacturers/' . $productData['Manufacturer']
            );
            if ($manufacturer == null) {
                self::$errorLog .= "Warning in the manufacturer name: in " .
                    $productName . " the manufacturer object of " .
                    $productData['Manufacturer'] . self::IS_MISSING;
                $fullySuccessful = false;
            } else {
                $productObj->setManufacturer([$manufacturer]);
            }

            $category = Utils::getObjectByPathIfExists(
                Category::class,
                '/Categories/' . $productData['Category']
            );
            if ($category === null) {
                self::$errorLog .= "Warning in the category name: in " .
                    $productName . " the category object of " .
                    $productData['Category'] . self::IS_MISSING;
                $productObj->setSubCategory([]);
                $fullySuccessful = false;
            } else {
                Utils::setCategoryAndSubCategories(
                    $productObj,
                    $category,
                    $productData['Category'],
                    $productData['Sub Categories']
                );
            }

            $productObj->setColor($productData['Color']);

            $productObj->setEnergyRating($productData['Energy Rating']);
            return $fullySuccessful;
        } catch (\Exception $e) {
            dump($e->getMessage());
        }
    }

    private static function setAssetData(Product $productObj, array $productData, $countryCode): void
    {
        $productObj->setMasterImage(Utils::getAsset($productData['Master Image Link']), $countryCode);
        Utils::addImagesToProductGallery($productData['Images Link'], $productObj, $countryCode);

        $video = Utils::getAsset($productData['Video Link']);
        if ($video !== null) {
            $videoPoster = Utils::getAsset($productData['Video Poster']);
            $videoTitle = $productData['Video Title'];
            $videoDescription = $productData['Video Description'];

            $videoData = new Video();
            $videoData->setData($video);
            $videoData->setType("asset");

            if ($videoPoster !== null) {
                $videoData->setPoster($videoPoster);
            }
            $videoData->setTitle($videoTitle);
            $videoData->setDescription($videoDescription);
            $productObj->setVideo($videoData);
        }
    }

    private static function setSalesData(
        Product $productObj,
        array $productData,
        string $countryCode,
        string $productName
    ): bool {
        $fullySuccessful = true;
        if (Utils::validateNumber($productData['Quantity Sold'])) {
            $productObj->setQuantitySold($productData['Quantity Sold'], $countryCode);
        } else {
            $fullySuccessful = false;
            self::$errorLog .= "Warning in the quantity sold: in " .
                $productName . " the quantity sold is empty or invalid.";
        }

        if (Utils::validateNumber($productData['Revenue'])) {
            $productObj->setRevenue($productData['Revenue'], $countryCode);
        } else {
            $fullySuccessful = false;
            self::$errorLog .= "Warning in the revenue: in " .
                $productName . " the revenue is empty or invalid.";
        }

        $productObj->setProductAvailablity(
            $productData['Product Availablity'] === 0 ? false : true,
            $countryCode
        );
        $productObj->setRating(
            Utils::validateNumber($productData['Rating']) && $productData['Rating'] <= 5 ? $productData['Rating'] : 0,
            $countryCode
        );

        return $fullySuccessful;
    }

    private static function setPricingData(
        Product $productObj,
        array $productData,
        string $countryCode,
        string $productName
    ): bool {
        $fullySuccessful = true;
        if (Utils::validateNumber($productData['Base Price'], 'price')) {
            $productObj->setBasePrice($productData['Base Price'], $countryCode);
        } else {
            $fullySuccessful = false;
            self::$errorLog .= "Warning in the base price: in " .
                $productName . " the base price is empty or invalid.";
        }

        if (Utils::validateNumber($productData['Selling Price'], 'price')) {
            $productObj->setSellingPrice($productData['Selling Price'], $countryCode);
        } else {
            $fullySuccessful = false;
            self::$errorLog .= "Warning in the selling price: in " .
                $productName . " the selling price is empty or invalid.";
        }

        $productObj->setDeliveryCharges(
            Utils::validateNumber($productData['Delivery Charges']) ? $productData['Delivery Charges'] : 0,
            $countryCode
        );
        $productObj->setTax(
            Utils::validateNumber($productData['Tax']) ? $productData['Tax'] : 0,
            $countryCode
        );
        $productObj->setDiscount(
            Utils::validateNumber($productData['Discount']) ? $productData['Discount'] : 0,
            $countryCode
        );

        return $fullySuccessful;
    }

    private static function setMeasurementsData(Product $productObj, array $productData, $productName): bool
    {
        $fullySuccessful = true;
        if (Utils::validateNumber($productData['Length'])) {
            $productObj->setLength($productData['Length']);
        } else {
            $fullySuccessful = false;
        }

        if (Utils::validateNumber($productData['Breadth'])) {
            $productObj->setBreadth($productData['Breadth']);
        } else {
            $fullySuccessful = false;
        }

        if (Utils::validateNumber($productData['Height'])) {
            $productObj->setHeight($productData['Height']);
        } else {
            $fullySuccessful = false;
        }
        $productObj->setDimensionUnit($productData['Dimension Unit']);

        if (Utils::validateNumber($productData['Size'])) {
            $productObj->setSize($productData['Size']);
        } else {
            $fullySuccessful = false;
        }
        $productObj->setSizeUnit($productData['Size Unit']);

        if (Utils::validateNumber($productData['Weight'])) {
            $productObj->setWeight($productData['Weight']);
        } else {
            $fullySuccessful = false;
        }
        $productObj->setWeightUnit($productData['Weight Unit']);

        if (!$fullySuccessful) {
            self::$errorLog .= "Warning in the measurements: in " .
                $productName . " Please verify measurments.";
        }

        return $fullySuccessful;
    }

    private static function setTechnicalDetailsData(Product $productObj, array $productData, string $productName): bool
    {
        $fullySuccessful = true;
        if (!empty($productData['Model Number'])) {
            $productObj->setModelNumber($productData['Model Number']);
        } else {
            self::$errorLog .= "Warning in the technical details: in " .
                $productName . " the model number is empty.";
        }

        $productObj->setModelYear($productData['Model Year']);
        $productObj->setModelName($productData['Model Name']);
        $productObj->setHardwareInterface($productData['Hardware Interface']);
        $productObj->setPowerSource($productData['Power Source']);
        $productObj->setVoltage($productData['Voltage']);
        $productObj->setWattage($productData['Wattage']);
        $productObj->setCountryOfOrigin($productData['Country Of Origin']);
        $productObj->setBatteriesRequired($productData['Batteries Required'] === 0 ? false : true);
        $productObj->setBatteriesIncluded($productData['Batteries Included'] === 0 ? false : true);

        return $fullySuccessful;
    }

    private static function setAdvanceTechnicalData(Product $productObj, array $productData): void
    {
        // For Camera
        $camera = null;
        if (!empty($productData[self::CAMERA])) {
            $cameraObj = Utils::getObjectByPathIfExists(Camera::class, self::CAMERAS_PATH . $productData[self::CAMERA]);
            $camera = ($cameraObj instanceof Camera) ? $cameraObj : null;
        }
        $productObj->setCamera($camera !== null ? [$camera] : $productObj->getCamera());

        // For Motherboard
        $motherboard = null;
        if (!empty($productData[self::MOTHERBOARD])) {
            $motherboardObj = Utils::getObjectByPathIfExists(
                MotherBoard::class,
                self::MOTHERBOARDS_PATH . $productData[self::MOTHERBOARD]
            );
            $motherboard = ($motherboardObj instanceof MotherBoard) ? $motherboardObj : null;
        }
        $productObj->setMotherboard($motherboard !== null ? [$motherboard] : $productObj->getMotherboard());

        $operatingSystem = null;
        if (!empty($productData[self::OPERATING_SYSTEM])) {
            $operatingSystemObj = Utils::getObjectByPathIfExists(
                OperatingSystem::class,
                self::OPERATING_SYSTEMS_PATH . $productData[self::OPERATING_SYSTEM]
            );
            $operatingSystem = ($operatingSystemObj instanceof OperatingSystem) ? $operatingSystemObj : null;
        }
        $productObj->setOperatingSystem(
            $operatingSystem !== null ? [$operatingSystem] : $productObj->getOperatingSystem()
        );

        // For Processor
        $processor = null;
        if (!empty($productData[self::PROCESSOR])) {
            $processorObj = Utils::getObjectByPathIfExists(
                Processor::class,
                self::PROCESSORS_PATH . $productData[self::PROCESSOR]
            );
            $processor = ($processorObj instanceof Processor) ? $processorObj : null;
        }
        $productObj->setProcessor($processor !== null ? [$processor] : $productObj->getProcessor());

        // For RAM
        $ram = null;
        if (!empty($productData[self::RAM])) {
            $ramObj = Utils::getObjectByPathIfExists(RAM::class, self::RAMS_PATH . $productData[self::RAM]);
            $ram = ($ramObj instanceof RAM) ? $ramObj : null;
        }
        $productObj->setRam($ram !== null ? [$ram] : $productObj->getRam());

        // For ROM
        $rom = null;
        if (!empty($productData[self::ROM])) {
            $romObj = Utils::getObjectByPathIfExists(ROM::class, self::ROMS_PATH . $productData[self::ROM]);
            $rom = ($romObj instanceof ROM) ? $romObj : null;
        }
        $productObj->setRom($rom !== null ? [$rom] : $productObj->getRom());

        // For Screen
        $screen = null;
        if (!empty($productData[self::SCREEN])) {
            $screenObj = Utils::getObjectByPathIfExists(Screen::class, self::SCREENS_PATH . $productData[self::SCREEN]);
            $screen = ($screenObj instanceof Screen) ? $screenObj : null;
        }
        $productObj->setScreen($screen !== null ? [$screen] : $productObj->getScreen());

        // For Sensors Set
        $sensorsSet = null;
        if (!empty($productData[self::SENSORS_SET])) {
            $sensorsSetObj = Utils::getObjectByPathIfExists(
                SensorsSet::class,
                self::SENSOR_SETS_PATH . $productData[self::SENSORS_SET]
            );
            $sensorsSet = ($sensorsSetObj instanceof SensorsSet) ? $sensorsSetObj : null;
        }
        $productObj->setSensorsSet($sensorsSet !== null ? [$sensorsSet] : $productObj->getSensorsSet());

        // For Speakers
        $speakers = null;
        if (!empty($productData[self::SPEAKERS])) {
            $speakersObj = Utils::getObjectByPathIfExists(
                Speakers::class,
                self::SPEAKERS_PATH . $productData[self::SPEAKERS]
            );
            $speakers = ($speakersObj instanceof Speakers) ? $speakersObj : null;
        }
        $productObj->setSpeakers($speakers !== null ? [$speakers] : $productObj->getSpeakers());

        // For SSD
        $ssd = null;
        if (!empty($productData[self::SSD])) {
            $ssdObj = Utils::getObjectByPathIfExists(SSD::class, self::SSD_PATH . $productData[self::SSD]);
            $ssd = ($ssdObj instanceof SSD) ? $ssdObj : null;
        }
        $productObj->setSsd($ssd !== null ? [$ssd] : $productObj->getSsd());

        // For HDD
        $hdd = null;
        if (!empty($productData[self::HDD])) {
            $hddObj = Utils::getObjectByPathIfExists(HDD::class, self::HDD_PATH . $productData[self::HDD]);
            $hdd = ($hddObj instanceof HDD) ? $hddObj : null;
        }
        $productObj->setHdd($hdd !== null ? [$hdd] : $productObj->getHdd());

        if (
            !empty($productData[self::CONNECTIVITY_TECHNOLGIES])
        ) {
            $productObj->setConnectivityTechnolgies([$productData[self::CONNECTIVITY_TECHNOLGIES]]);
        }
    }

    private static function setClassificationStore(Product $productObj, array $productData): void
    {
        $availableGroups = [
            "Gaming and Entertainment" => 3,
            "Photography Enthusiasts" => 4,
            "Budget Conscious Users" => 5,
            "Business Productivity" => 1
        ];
        $groups = $productData['Classification Groups'];
        $groupNames = explode(',', $groups);
        $groupNames = array_map('trim', $groupNames);
        $activeGroups = [];
        foreach ($groupNames as $groupName) {
            if (array_key_exists($groupName, $availableGroups)) {
                $activeGroups[$availableGroups[$groupName]] = true;
            }
        }
        $productObj->getProductUsageScenarios()->setActiveGroups($activeGroups);
    }

    private static function logProductSummary()
    {
        Utils::logSummary(
            self::ASSET_FILENAME,
            self::ASSET_FILE_PATH,
            self::PARENT_DIRECTORY_PATH,
            self::$totalObjects,
            self::$partialFailed,
            self::$completelyFailed,
            self::$fullySuccessful
        );

        Utils::logError(
            self::ERROR_ASSET_FILENAME,
            self::ERROR_ASSET_FILE_PATH,
            self::ERROR_PARENT_DIRECTORY_PATH,
            self::$errorLog
        );
    }
}
