<?php

namespace App\Utils;

use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Data\Video;

class ProductStorageMethods
{
    const PRODUCTS_PATH = "/Products/";
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

    // Properties for tracking import status
    private static $totalObjects = 0;
    private static $partialFailed = 0;
    private static $completelyFailed = 0;
    private static $fullySuccessful = 0;
    private static $errorLog = "";

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

    private static function compareProductData($a, $b)
    {
        $isEmptyParentA = empty($a['Type']);
        $isEmptyParentB = empty($b['Type']);

        if ($isEmptyParentA !== $isEmptyParentB) {
            return $isEmptyParentA ? -1 : 1;
        }

        return 0;
    }

    private static function handleEmptyTypeVariant($productData)
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

    private static function handleNonVariantEmptyFields($productData)
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
        $fullySuccessful = self::setBaseData($type, $productObj, $productData, $countryCode, $productName);
        self::setAssetData($productObj, $productData, $countryCode);
        self::setSalesData($productObj, $productData, $countryCode);
        self::setPricingData($productObj, $productData, $countryCode);
        self::setMeasurementsData($productObj, $productData);
        self::setTechnicalDetailsData($productObj, $productData);
        self::setAdvanceTechnicalData($productObj, $productData);

        if ($fullySuccessful) {
            self::$fullySuccessful++;
        } else {
            self::$partialFailed++;
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

            $brand = Utils::getBrandIfExists('/Brands/' . $productData['Brand']);
            if ($brand == null) {
                self::$errorLog .= "Warning in the brand name: in " .
                    $productName . " the brand object of " .
                    $productData['Brand'] . self::IS_MISSING;
                $fullySuccessful = false;
            } else {
                $productObj->setBrand([$brand]);
            }
            $manufacturer = Utils::getManufacturerIfExists('/Manufacturers/' . $productData['Manufacturer']);
            if ($manufacturer == null) {
                self::$errorLog .= "Warning in the manufacturer name: in " .
                    $productName . " the manufacturer object of " .
                    $productData['Manufacturer'] . self::IS_MISSING;
                $fullySuccessful = false;
            } else {
                $productObj->setManufacturer([$manufacturer]);
            }

            $category = Utils::getCategoryIfExist('/Categories/' . $productData['Category']);
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

    private static function setSalesData(Product $productObj, array $productData, string $countryCode)
    {
        $productObj->setQuantitySold(
            Utils::validateNumber(
                $productData['Quantity Sold']
            ) ? $productData['Quantity Sold'] : 0,
            $countryCode
        );
        $productObj->setRevenue(
            Utils::validateNumber($productData['Revenue'])
                ? $productData['Revenue'] : 0,
            $countryCode
        );
        $productObj->setProductAvailablity(
            $productData['Product Availablity'] === 0 ? false : true,
            $countryCode
        );
        $productObj->setRating(
            Utils::validateNumber($productData['Rating']) && $productData['Rating'] <= 5 ? $productData['Rating'] : 0,
            $countryCode
        );
    }

    private static function setPricingData(Product $productObj, array $productData, string $countryCode): void
    {
        $productObj->setBasePrice(
            Utils::validateNumber($productData['Base Price']) ? $productData['Base Price'] : 0,
            $countryCode
        );
        $productObj->setSellingPrice(
            Utils::validateNumber($productData['Selling Price']) ? $productData['Selling Price'] : 0,
            $countryCode
        );
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
    }

    private static function setMeasurementsData(Product $productObj, array $productData): void
    {
        $productObj->setLength(
            Utils::validateNumber($productData['Length']) ? $productData['Length'] : 0
        );

        $productObj->setBreadth(
            Utils::validateNumber($productData['Breadth']) ? $productData['Breadth'] : 0
        );
        $productObj->setHeight(
            Utils::validateNumber($productData['Height']) ? $productData['Height'] : 0
        );
        $productObj->setDimensionUnit($productData['Dimension Unit']);

        $productObj->setSize(
            Utils::validateNumber($productData['Size']) ? $productData['Size'] : 0
        );
        $productObj->setSizeUnit($productData['Size Unit']);

        $productObj->setWeight(
            Utils::validateNumber($productData['Weight']) ? $productData['Weight'] : 0
        );
        $productObj->setWeightUnit($productData['Weight Unit']);
    }

    private static function setTechnicalDetailsData(Product $productObj, array $productData): void
    {
        $productObj->setModelNumber($productData['Model Number']);
        $productObj->setModelYear($productData['Model Year']);
        $productObj->setModelName($productData['Model Name']);
        $productObj->setHardwareInterface($productData['Hardware Interface']);
        $productObj->setPowerSource($productData['Power Source']);
        $productObj->setVoltage($productData['Voltage']);
        $productObj->setWattage($productData['Wattage']);
        $productObj->setCountryOfOrigin($productData['Country Of Origin']);
        $productObj->setBatteriesRequired($productData['Batteries Required'] === 0 ? false : true);
        $productObj->setBatteriesIncluded($productData['Batteries Included'] === 0 ? false : true);
    }

    private static function setAdvanceTechnicalData(Product $productObj, array $productData): void
    {
        if (isset($productData[self::CAMERA]) && !empty($productData[self::CAMERA])) {
            $camera = Utils::getCameraIfExist('/Cameras/' . $productData[self::CAMERA]);
            if ($camera !== null) {
                $productObj->setCamera([$camera]);
            }
        }

        if (isset($productData[self::MOTHERBOARD]) && !empty($productData[self::MOTHERBOARD])) {
            $motherboard = Utils::getMotherboardIfExist('/Motherboards/' . $productData[self::MOTHERBOARD]);
            if ($motherboard !== null) {
                $productObj->setMotherboard([$motherboard]);
            }
        }

        if (isset($productData[self::OPERATING_SYSTEM]) && !empty($productData[self::OPERATING_SYSTEM])) {
            $os = Utils::getOperatingSystemIfExist('/Operating Systems/' . $productData[self::OPERATING_SYSTEM]);
            if ($os !== null) {
                $productObj->setOperatingSystem([$os]);
            }
        }

        if (isset($productData[self::PROCESSOR]) && !empty($productData[self::PROCESSOR])) {
            $processor = Utils::getProcessorIfExist('/Processors/' . $productData[self::PROCESSOR]);
            if ($processor !== null) {
                $productObj->setProcessor([$processor]);
            }
        }

        if (isset($productData[self::RAM]) && !empty($productData[self::RAM])) {
            $ram = Utils::getRAMIfExist('/RAMs/' . $productData[self::RAM]);
            if ($ram !== null) {
                $productObj->setRam([$ram]);
            }
        }

        if (isset($productData[self::ROM]) && !empty($productData[self::ROM])) {
            $rom = Utils::getROMIfExist('/ROMs/' . $productData[self::ROM]);
            if ($rom !== null) {
                $productObj->setRom([$rom]);
            }
        }

        if (isset($productData[self::SCREEN]) && !empty($productData[self::SCREEN])) {
            $screen = Utils::getScreenIfExist('/Screens/' . $productData[self::SCREEN]);
            if ($screen !== null) {
                $productObj->setScreen([$screen]);
            }
        }

        if (isset($productData[self::SENSORS_SET]) && !empty($productData[self::SENSORS_SET])) {
            $sensorsSet = Utils::getSensorsSetIfExist('/Sensor Sets/' . $productData[self::SENSORS_SET]);
            if ($sensorsSet !== null) {
                $productObj->setSensorsSet([$sensorsSet]);
            }
        }

        if (isset($productData[self::SPEAKERS]) && !empty($productData[self::SPEAKERS])) {
            $speakers = Utils::getSpeakersIfExist('/Speakers/' . $productData[self::SPEAKERS]);
            if ($speakers !== null) {
                $productObj->setSpeakers([$speakers]);
            }
        }

        if (isset($productData[self::SSD]) && !empty($productData[self::SSD])) {
            $ssd = Utils::getSSDIfExist('/SSDs/' . $productData[self::SSD]);
            if ($ssd !== null) {
                $productObj->setSsd([$ssd]);
            }
        }

        if (isset($productData[self::HDD]) && !empty($productData[self::HDD])) {
            $hdd = Utils::getHDDIfExist('/HDDs/' . $productData[self::HDD]);
            if ($hdd !== null) {
                $productObj->setHdd([$hdd]);
            }
        }

        if (
            isset($productData[self::CONNECTIVITY_TECHNOLGIES])
            && !empty($productData[self::CONNECTIVITY_TECHNOLGIES])
        ) {
            $productObj->setConnectivityTechnolgies([$productData[self::CONNECTIVITY_TECHNOLGIES]]);
        }
    }

    private static function logProductSummary()
    {
        Utils::logSummary(
            "Products Import Summary.txt",
            "/Logs/Products/Products Import Summary.txt",
            "/Logs/Products",
            self::$totalObjects,
            self::$partialFailed,
            self::$completelyFailed,
            self::$fullySuccessful
        );

        Utils::logError(
            "Products Error Report.txt",
            "/Logs/Products/Products Error Report.txt",
            "/Logs/Products",
            self::$errorLog
        );
    }
}
