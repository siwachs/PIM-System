<?php

namespace App\Utils;

use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Data\Video;

class ProductStorageMethods
{
    const IS_MISSING = " is missing.\n";
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

        foreach ($productArray as $productData) {
            try {
                $productName = $productData['Object Name'];
                if (
                    empty($productData['Name']) ||
                    empty($productData['SKU']) ||
                    !preg_match('/^SKU\d+$/', $productData['SKU'])
                ) {
                    self::$completelyFailed++;
                    self::$errorLog .= "Error in " . $productName . ". The name or SKU field is empty or invalid.\n";
                    continue;
                }


                $productObj = self::fetchProduct($productData['SKU']);

                if ($productObj instanceof Product) {
                    self::updateProduct($productName, $productData, $countryCode, $productObj);
                } else {
                    self::createProduct($productName, $productData, $countryCode);
                }
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
        }

        self::logProductSummary();
    }

    private static function fetchProduct($sku)
    {
        $product = new Product\Listing();
        $product->setLimit(1);
        $product->setUnpublished(true);
        $product->filterBySku($sku);
        $filtredProduct = current(iterator_to_array($product));
        return $filtredProduct instanceof Product ? $filtredProduct : null;
    }

    private static function updateProduct($productName, $productData, $countryCode, $productObj)
    {
        self::mapProductData($productName, $productData, $countryCode, $productObj);
        $productObj->setPublished(false);
        $productObj->save();
    }

    private static function createProduct($productName, $productData, $countryCode)
    {
        $newProduct = new Product();
        $newProduct->setKey(\Pimcore\Model\Element\Service::getValidKey($productName, 'object'));
        $parentId = Utils::getOrCreateFolderIdByPath("/Products/" . $productData['Parent'], 1);
        $newProduct->setParentId($parentId);
        self::mapProductData($productName, $productData, $countryCode, $newProduct);
        $newProduct->save();
    }

    private static function setBaseData(
        Product $productObj,
        array $productData,
        string $countryCode,
        string $productName
    ): bool {
        $fullySuccessful = true;
        $productObj->setSku($productData['SKU']);
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
    }

    private static function setAssetData(Product $productObj, array $productData, $countryCode): void
    {
        $productObj->setMasterImage(Utils::getAsset($productData['Master Image Link']), $countryCode);
        Utils::addImagesToProductGallery($productData['Images Link'], $productObj, $countryCode);
        $video = Utils::getAsset($productData['Video Link']);
        if ($video !== null) {
            $videoPoster = Utils::getAsset($productData['Video Poster']);
            $videoTitle = Utils::getAsset($productData['Video Title']);
            $videoDescription = Utils::getAsset($productData['Video Description']);

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
        if (isset($productData['Camera']) && !empty($productData['Camera'])) {
            $camera = Utils::getCameraIfExist('/Cameras/' . $productData['Camera']);
            if ($camera !== null) {
                $productObj->setCamera([$camera]);
            }
        }
        if (isset($productData['Motherboard']) && !empty($productData['Motherboard'])) {
            $motherboard = Utils::getMotherboardIfExist('/Motherboards/' . $productData['Motherboard']);
            if ($motherboard !== null) {
                $productObj->setMotherboard([$motherboard]);
            }
        }
        if (isset($productData['OperatingSystem']) && !empty($productData['OperatingSystem'])) {
            $os = Utils::getOperatingSystemIfExist('/OperatingSystems/' . $productData['OperatingSystem']);
            if ($os !== null) {
                $productObj->setOperatingSystem([$os]);
            }
        }
        if (isset($productData['Processor']) && !empty($productData['Processor'])) {
            $processor = Utils::getProcessorIfExist('/Processors/' . $productData['Processor']);
            if ($processor !== null) {
                $productObj->setProcessor([$processor]);
            }
        }
        if (isset($productData['RAM']) && !empty($productData['RAM'])) {
            $ram = Utils::getRAMIfExist('/RAMs/' . $productData['RAM']);
            if ($ram !== null) {
                $productObj->setRAM([$ram]);
            }
        }
        if (isset($productData['ROM']) && !empty($productData['ROM'])) {
            $rom = Utils::getROMIfExist('/ROMs/' . $productData['ROM']);
            if ($rom !== null) {
                $productObj->setROM([$rom]);
            }
        }
        if (isset($productData['Screen']) && !empty($productData['Screen'])) {
            $screen = Utils::getScreenIfExist('/Screens/' . $productData['Screen']);
            if ($screen !== null) {
                $productObj->setScreen([$screen]);
            }
        }
        if (isset($productData['SensorsSet']) && !empty($productData['SensorsSet'])) {
            $sensorsSet = Utils::getSensorsSetIfExist('/SensorsSets/' . $productData['SensorsSet']);
            if ($sensorsSet !== null) {
                $productObj->setSensorsSet([$sensorsSet]);
            }
        }
        if (isset($productData['Speakers']) && !empty($productData['Speakers'])) {
            $speakers = Utils::getSpeakersIfExist('/Speakers/' . $productData['Speakers']);
            if ($speakers !== null) {
                $productObj->setSpeakers([$speakers]);
            }
        }
        if (isset($productData['SSD']) && !empty($productData['SSD'])) {
            $ssd = Utils::getSSDIfExist('/SSDs/' . $productData['SSD']);
            if ($ssd !== null) {
                $productObj->setSSD([$ssd]);
            }
        }
        if (isset($productData['HDD']) && !empty($productData['HDD'])) {
            $hdd = Utils::getHDDIfExist('/HDDs/' . $productData['HDD']);
            if ($hdd !== null) {
                $productObj->setHDD([$hdd]);
            }
        }
        if (
            isset($productData[self::CONNECTIVITY_TECHNOLGIES])
            && !empty($productData[self::CONNECTIVITY_TECHNOLGIES])
        ) {
            $productObj->setConnectivityTechnolgies([$productData[self::CONNECTIVITY_TECHNOLGIES]]);
        }
    }

    private static function mapProductData(
        string $productName,
        array $productData,
        string $countryCode,
        Product $productObj
    ) {
        $fullySuccessful = self::setBaseData($productObj, $productData, $countryCode, $productName);
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
