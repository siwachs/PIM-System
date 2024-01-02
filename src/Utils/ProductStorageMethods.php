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

            if ($type === 'Variant') {
                $productObj->setColor($productData['Color']);
            }

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
        if (Utils::validatePrice($productData['Base Price'])) {
            $productObj->setBasePrice($productData['Base Price'], $countryCode);
        } else {
            $fullySuccessful = false;
            self::$errorLog .= "Warning in the base price: in " .
                $productName . " the base price is empty or invalid.";
        }

        if (Utils::validatePrice($productData['Selling Price'])) {
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
        if (
            isset($productData[self::CAMERA]) &&
            !empty($productData[self::CAMERA]) &&
            ($camera = Utils::getCameraIfExist('/Cameras/' . $productData[self::CAMERA])) !== null
        ) {
            $productObj->setCamera([$camera]);
        }

        if (
            isset($productData[self::MOTHERBOARD]) &&
            !empty($productData[self::MOTHERBOARD]) &&
            ($motherboard = Utils::getMotherboardIfExist('/Motherboards/' . $productData[self::MOTHERBOARD])) !== null
        ) {
            $productObj->setMotherboard([$motherboard]);
        }

        if (
            isset($productData[self::OPERATING_SYSTEM]) &&
            !empty($productData[self::OPERATING_SYSTEM]) &&
            ($os = Utils::getOperatingSystemIfExist(
                '/Operating Systems/' . $productData[self::OPERATING_SYSTEM]
            )) !== null
        ) {
            $productObj->setOperatingSystem([$os]);
        }

        if (
            isset($productData[self::PROCESSOR]) &&
            !empty($productData[self::PROCESSOR]) &&
            ($processor = Utils::getProcessorIfExist('/Processors/' . $productData[self::PROCESSOR])) !== null
        ) {
            $productObj->setProcessor([$processor]);
        }

        if (isset($productData[self::RAM]) && !empty($productData[self::RAM]) && ($ram = Utils::getRAMIfExist(
            '/RAMs/' . $productData[self::RAM]
        )) !== null) {
            $productObj->setRam([$ram]);
        }

        if (isset($productData[self::ROM]) && !empty($productData[self::ROM]) && ($rom = Utils::getROMIfExist(
            '/ROMs/' . $productData[self::ROM]
        )) !== null) {
            $productObj->setRom([$rom]);
        }

        if (
            isset($productData[self::SCREEN]) &&
            !empty($productData[self::SCREEN]) &&
            ($screen = Utils::getScreenIfExist(
                '/Screens/' . $productData[self::SCREEN]
            )) !== null
        ) {
            $productObj->setScreen([$screen]);
        }

        if (
            isset($productData[self::SENSORS_SET]) &&
            !empty($productData[self::SENSORS_SET]) &&
            ($sensorsSet = Utils::getSensorsSetIfExist(
                '/Sensor Sets/' . $productData[self::SENSORS_SET]
            )) !== null
        ) {
            $productObj->setSensorsSet([$sensorsSet]);
        }

        if (
            isset($productData[self::SPEAKERS]) &&
            !empty($productData[self::SPEAKERS]) &&
            ($speakers = Utils::getSpeakersIfExist(
                '/Speakers/' . $productData[self::SPEAKERS]
            )) !== null
        ) {
            $productObj->setSpeakers([$speakers]);
        }

        if (
            isset($productData[self::SSD]) &&
            !empty($productData[self::SSD]) &&
            ($ssd = Utils::getSSDIfExist('/SSDs/' . $productData[self::SSD])) !== null
        ) {
            $productObj->setSsd([$ssd]);
        }

        if (
            isset($productData[self::HDD]) &&
            !empty($productData[self::HDD]) &&
            ($hdd = Utils::getHDDIfExist('/HDDs/' . $productData[self::HDD])) !== null
        ) {
            $productObj->setHdd([$hdd]);
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
