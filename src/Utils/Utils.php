<?php

namespace App\Utils;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Pimcore\Model\DataObject;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\DataObject\Data\ImageGallery;
use Pimcore\Model\DataObject\Data\Hotspotimage;
use Pimcore\Model\DataObject\Brand;
use Pimcore\Model\DataObject\Camera;
use Pimcore\Model\DataObject\MotherBoard;
use Pimcore\Model\DataObject\RAM;
use Pimcore\Model\DataObject\ROM;
use Pimcore\Model\DataObject\SensorsSet;
use Pimcore\Model\DataObject\Screen;
use Pimcore\Model\DataObject\Processor;
use Pimcore\Model\DataObject\OperatingSystem;
use Pimcore\Model\DataObject\Speakers;
use Pimcore\Model\DataObject\SSD;
use Pimcore\Model\DataObject\HDD;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Manufacturer;
use Pimcore\Model\DataObject\Data\Link;
use Pimcore\Model\Notification\Service\NotificationService;

class BaseClass
{
    /**
     * Get folder ID by path. If the folder exists, return its ID; otherwise, create the folder and return its ID.
     *
     * @param string $folderPath The path of the folder
     * @param int $parentId The ID of the parent folder
     *
     * @return int The ID of the folder (existing or newly created)
     */
    public static function getOrCreateFolderIdByPath(string $folderPath, int $parentId): int
    {
        $folder = Folder::getByPath($folderPath);

        if ($folder instanceof Folder) {
            return $folder->getId();
        }

        $currentParentId = $parentId;
        $folderNames = array_map('trim', explode('/', $folderPath));

        foreach ($folderNames as $folderName) {
            $fullPath = '/' . implode('/', array_slice($folderNames, 0, array_search($folderName, $folderNames) + 1));
            $folder = Folder::getByPath($fullPath);

            if ($folder instanceof Folder) {
                $currentParentId = $folder->getId();
            } else {
                $newFolder = new Folder();
                $newFolder->setKey($folderName);
                $newFolder->setParentId($currentParentId);
                $newFolder->save();
                $currentParentId = $newFolder->getId();
            }
        }

        return $currentParentId;
    }

    /**
     * Get Asset by Path
     *
     * @param string $assetPath The path to the asset
     * @return Asset|null Returns the Asset object or null if not found
     */
    public static function getAsset(string $assetPath): ?Asset
    {
        try {
            return Asset::getByPath($assetPath);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Adds images to the product's image gallery.
     *
     * @param string $imageString A string of comma-separated image paths.
     * @param Product $productObj The Pimcore product object.
     */
    public static function addImagesToProductGallery(string $imageString, Product $productObj, $countryCode): void
    {
        $galleryData = array_map('trim', explode(',', $imageString));
        $items = [];

        foreach ($galleryData as $imageLink) {
            if (count($items) >= 5) {
                break;
            }

            $image = self::getAsset($imageLink);
            if ($image !== null) {
                $advancedImage = new Hotspotimage();
                $advancedImage->setImage($image);
                $items[] = $advancedImage;
            }
        }

        $productObj->setImages(new ImageGallery($items), $countryCode);
    }

    /**
     * Convert Spreadsheet Sheet to Associative Array
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet The spreadsheet sheet object
     * @return array Returns an array representing the sheet data as associative arrays
     */
    public static function sheetToAssocArray($sheet): array
    {
        try {
            $data = [];
            $headerRow = [];
            $highestRow = $sheet->getHighestDataRow();
            $highestColumn = $sheet->getHighestDataColumn();
            $lastColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            // Get the header row values
            for ($columnIndex = 1; $columnIndex <= $lastColumnIndex; $columnIndex++) {
                $cellCoordinate = Coordinate::stringFromColumnIndex($columnIndex) . 1;
                $cellValue = $sheet->getCell($cellCoordinate)->getValue();
                $headerRow[] = trim($cellValue);
            }

            // Read each row and create associative array with keys from header row
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($columnIndex = 1; $columnIndex <= $lastColumnIndex; $columnIndex++) {
                    $cellCoordinate = Coordinate::stringFromColumnIndex($columnIndex) . $row;
                    $cellValue = $sheet->getCell($cellCoordinate)->getValue();
                    $rowData[$headerRow[$columnIndex - 1]] = trim($cellValue);
                }
                $data[] = $rowData; // Push each row as an associative array to the main array
            }

            return $data;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if founded year is valid (not greater than current year and must be numeric)
     *
     * @param int $yearFounded The year the brand was founded
     * @return bool Returns true if the year is valid (not greater than the current year), otherwise false
     */
    public static function isValidYearFounded($yearFounded): bool
    {
        $currentYear = date('Y');
        return is_numeric($yearFounded) && $yearFounded > 0 && $yearFounded <= $currentYear;
    }

    /**
     * Validates if a value is a number and greater than or equal to zero.
     *
     * @param mixed $value The value to be validated
     *
     * @return bool Returns true if the value is a number and greater than or equal to zero; otherwise, false.
     */
    public static function validateNumber($value): bool
    {
        return is_numeric($value) && floatval($value) >= 0;
    }

    /**
     * Creates a Pimcore Link object representing a Social Media Link.
     *
     * @param string $url   The URL of the social media link.
     * @param string $text  The text or label associated with the link.
     * @param string $title The title attribute for the link.
     *
     * @return Link|null Returns a Pimcore Link object if the URL is not empty, otherwise returns null.
     */
    public static function getSocialMediaLinkObject($url, $text, $title): ?Link
    {
        if (empty($url)) {
            return null;
        }

        $link = new Link();
        $link->setPath($url ?? "");
        $link->setText($text ?? "");
        $link->setTitle($title ?? "");

        return $link;
    }

    /**
     * Logs a summary of the imported data.
     *
     * @param string $assetFilename   The filename for the summary asset.
     * @param string $assetFilePath   The file path for the summary asset.
     * @param string $parentIdPath    The path of the parent directory for the asset.
     * @param int    $totalObjects    Total count of objects processed.
     * @param int    $partialFailed   Count of partially failed objects.
     * @param int    $completelyFailed Count of completely failed objects.
     * @param int    $fullySuccessful Count of fully successful objects.
     *
     * @throws \Exception If there's an issue while handling the asset or its data.
     */
    public static function logSummary(
        $assetFilename,
        $assetFilePath,
        $parentIdPath,
        $totalObjects,
        $partialFailed,
        $completelyFailed,
        $fullySuccessful
    ) {
        try {
            $existingAsset = \Pimcore\Model\Asset::getByPath($assetFilePath);
            $content = "";
            $content .= "Total Objects: " . $totalObjects . "\n" . "Partial Failed Objects: " . $partialFailed . "\n";
            $content .= "Completely Failed Objects: " . $completelyFailed . "\n";
            $content .= "Fully Successful Objects: " . $fullySuccessful;

            if (!$existingAsset instanceof \Pimcore\Model\Asset) {
                $asset = new \Pimcore\Model\Asset();
                $asset->setFilename($assetFilename);
                $asset->setData($content);
                $asset->setParent(\Pimcore\Model\Asset::getByPath($parentIdPath) ??
                    \Pimcore\Model\Asset::getByPath("/"));
                $asset->save();
            } else {
                $existingAsset->setData($content);
                $existingAsset->save();
            }
        } catch (\Exception $e) {
            dump($e->getMessage());
        }
    }


    /**
     * Logs an error report or messages.
     *
     * @param string $assetFilename   The filename for the error report asset.
     * @param string $assetFilePath   The file path for the error report asset.
     * @param string $parentIdPath    The path of the parent directory for the asset.
     * @param string $content         The error report or messages content.
     *
     * @throws \Exception If there's an issue while handling the asset or its data.
     */
    public static function logError(
        $assetFilename,
        $assetFilePath,
        $parentIdPath,
        $content
    ) {
        try {
            $existingAsset = \Pimcore\Model\Asset::getByPath($assetFilePath);

            if (!$existingAsset instanceof \Pimcore\Model\Asset) {
                $asset = new \Pimcore\Model\Asset();
                $asset->setFilename($assetFilename);
                $asset->setData($content);
                $asset->setParent(\Pimcore\Model\Asset::getByPath($parentIdPath) ??
                    \Pimcore\Model\Asset::getByPath("/"));
                $asset->save();
            } else {
                $existingAsset->setData($content);
                $existingAsset->save();
            }
        } catch (\Exception $e) {
            dump($e->getMessage());
        }
    }

    public static function uploadToAssets(
        $assetFilename,
        $assetFilePath,
        $parentIdPath,
        $localPath
    ) {
        $existingAsset = \Pimcore\Model\Asset::getByPath($assetFilePath);
        if (!$existingAsset instanceof \Pimcore\Model\Asset) {
            $newAsset = new \Pimcore\Model\Asset();
            $newAsset->setFilename($assetFilename);
            $newAsset->setData(file_get_contents($localPath));
            $newAsset->setParent(\Pimcore\Model\Asset::getByPath($parentIdPath)
                ?? \Pimcore\Model\Asset::getByPath("/"));
            $newAsset->save();
        } else {
            $existingAsset->setData(file_get_contents($localPath));
            $existingAsset->save();
        }
    }


    /**
     * Sends a notification to a user.
     *
     * @param NotificationService $notificationService The Pimcore Notification Service
     * @param int $sender The sender ID
     * @param int $receiver The receiver ID
     * @param string $title The title of the notification
     * @param string $message The message content of the notification
     *
     * @throws \Exception
     */
    public static function sendNotification(
        NotificationService $notificationService,
        int $sender,
        int $receiver,
        string $title,
        string $message
    ) {
        $notificationService->sendToUser($receiver, $sender, $title, $message);
    }
}

class Utils extends BaseClass
{
    /**
     * Get Brand by Path if it exists, otherwise return null.
     *
     * @param string $brandPath The path to the Brand object
     *
     * @return Brand|null The Brand object if found, otherwise null
     */
    public static function getBrandIfExists(string $brandPath): ?Brand
    {
        try {
            // Try to retrieve the Brand object by its path
            $brandObj = Brand::getByPath($brandPath);
            return $brandObj instanceof Brand ? $brandObj : null;
        } catch (\Exception $e) {
            // Handle exceptions, log errors, or return null based on your requirement
            return null;
        }
    }

    /**
     * Get Manufacturer by Path if it exists, otherwise return null.
     *
     * @param string $manufacturerPath The path to the Manufacturer object
     *
     * @return Manufacturer|null The Manufacturer object if found, otherwise null
     */
    public static function getManufacturerIfExists(string $manufacturerPath): ?Manufacturer
    {
        try {
            // Try to retrieve the Manufacturer object by its path
            $manufacturerObj = Manufacturer::getByPath($manufacturerPath);
            return $manufacturerObj instanceof Manufacturer ? $manufacturerObj : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getCategoryIfExist(string $categoryPath): ?Category
    {
        try {
            $categoryObj = Category::getByPath($categoryPath);
            return $categoryObj instanceof Category ? $categoryObj : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Retrieves the object for the Camera class by its path if it exists.
     *
     * @param string $path The path of the Camera object.
     * @return Camera|null The Camera object if found, otherwise null.
     */
    public static function getCameraIfExist(string $path): ?Camera
    {
        try {
            $cameraObj = Camera::getByPath($path);
            return $cameraObj instanceof Camera ? $cameraObj : null;
        } catch (\Exception $e) {
            // Handle any exceptions that occur during retrieval
            return null;
        }
    }

    /**
     * Retrieves the object for the Motherboard class by its path if it exists.
     *
     * @param string $path The path of the Motherboard object.
     * @return Motherboard|null The Motherboard object if found, otherwise null.
     */
    public static function getMotherboardIfExist(string $path): ?Motherboard
    {
        try {
            $motherboardObj = Motherboard::getByPath($path);
            return $motherboardObj instanceof Motherboard ? $motherboardObj : null;
        } catch (\Exception $e) {
            // Handle any exceptions that occur during retrieval
            return null;
        }
    }

    /**
     * Retrieves the object for the OperatingSystem class by its path if it exists.
     *
     * @param string $path The path of the OperatingSystem object.
     * @return OperatingSystem|null The OperatingSystem object if found, otherwise null.
     */
    public static function getOperatingSystemIfExist(string $path): ?OperatingSystem
    {
        try {
            $osObj = OperatingSystem::getByPath($path);
            return $osObj instanceof OperatingSystem ? $osObj : null;
        } catch (\Exception $e) {
            // Handle any exceptions that occur during retrieval
            return null;
        }
    }

    /**
     * Retrieves the object for the Processor class by its path if it exists.
     *
     * @param string $path The path of the Processor object.
     * @return Processor|null The Processor object if found, otherwise null.
     */
    public static function getProcessorIfExist(string $path): ?Processor
    {
        try {
            $processorObj = Processor::getByPath($path);
            return $processorObj instanceof Processor ? $processorObj : null;
        } catch (\Exception $e) {
            // Handle any exceptions that occur during retrieval
            return null;
        }
    }

    /**
     * Retrieves the object for the RAM class by its path if it exists.
     *
     * @param string $path The path of the RAM object.
     * @return RAM|null The RAM object if found, otherwise null.
     */
    public static function getRAMIfExist(string $path): ?RAM
    {
        try {
            $ramObj = RAM::getByPath($path);
            return $ramObj instanceof RAM ? $ramObj : null;
        } catch (\Exception $e) {
            // Handle any exceptions that occur during retrieval
            return null;
        }
    }

    /**
     * Retrieves the object for the ROM class by its path if it exists.
     *
     * @param string $path The path of the ROM object.
     * @return ROM|null The ROM object if found, otherwise null.
     */
    public static function getROMIfExist(string $path): ?ROM
    {
        try {
            $romObj = ROM::getByPath($path);
            return $romObj instanceof ROM ? $romObj : null;
        } catch (\Exception $e) {
            // Handle any exceptions that occur during retrieval
            return null;
        }
    }

    /**
     * Retrieves the object for the Screen class by its path if it exists.
     *
     * @param string $path The path of the Screen object.
     * @return Screen|null The Screen object if found, otherwise null.
     */
    public static function getScreenIfExist(string $path): ?Screen
    {
        try {
            $screenObj = Screen::getByPath($path);
            return $screenObj instanceof Screen ? $screenObj : null;
        } catch (\Exception $e) {
            // Handle any exceptions that occur during retrieval
            return null;
        }
    }

    /**
     * Retrieves the object for the Sensors Set class by its path if it exists.
     *
     * @param string $path The path of the Sensors Set object.
     * @return SensorsSet|null The Sensors Set object if found, otherwise null.
     */
    public static function getSensorsSetIfExist(string $path): ?SensorsSet
    {
        try {
            $sensorsSetObj = SensorsSet::getByPath($path);
            return $sensorsSetObj instanceof SensorsSet ? $sensorsSetObj : null;
        } catch (\Exception $e) {
            // Handle any exceptions that occur during retrieval
            return null;
        }
    }

    /**
     * Retrieves the object for the Speakers class by its path if it exists.
     *
     * @param string $path The path of the Speakers object.
     * @return Speakers|null The Speakers object if found, otherwise null.
     */
    public static function getSpeakersIfExist(string $path): ?Speakers
    {
        try {
            $speakersObj = Speakers::getByPath($path);
            return $speakersObj instanceof Speakers ? $speakersObj : null;
        } catch (\Exception $e) {
            // Handle any exceptions that occur during retrieval
            return null;
        }
    }

    /**
     * Retrieves the object for the SSD class by its path if it exists.
     *
     * @param string $path The path of the SSD object.
     * @return SSD|null The SSD object if found, otherwise null.
     */
    public static function getSSDIfExist(string $path): ?SSD
    {
        try {
            $ssdObj = SSD::getByPath($path);
            return $ssdObj instanceof SSD ? $ssdObj : null;
        } catch (\Exception $e) {
            // Handle any exceptions that occur during retrieval
            return null;
        }
    }

    /**
     * Retrieves the object for the HDD class by its path if it exists.
     *
     * @param string $path The path of the HDD object.
     * @return HDD|null The HDD object if found, otherwise null.
     */
    public static function getHDDIfExist(string $path): ?HDD
    {
        try {
            $hddObj = HDD::getByPath($path);
            return $hddObj instanceof HDD ? $hddObj : null;
        } catch (\Exception $e) {
            // Handle any exceptions that occur during retrieval
            return null;
        }
    }

    public static function setCategoryAndSubCategories(
        Product $productObj,
        Category $category,
        $productCategory,
        $subCategoriesString
    ): void {
        $productObj->setCategory([$category]);
        $subCategories = array_map('trim', explode(',', $subCategoriesString));

        $subCategoriesArray = [];
        foreach ($subCategories as $subCategory) {
            $subCategoryObj = Category::getByPath('/Categories/' . $productCategory . '/' . $subCategory);
            if ($subCategoryObj !== null) {
                $subCategoriesArray[] = $subCategoryObj;
            }
        }

        $categoryVariants = $category->getChildren([DataObject::OBJECT_TYPE_VARIANT]);
        $categoryVariantIds = array_map(fn ($variant) => $variant->getId(), iterator_to_array($categoryVariants));

        $subCategoriesArray = array_filter($subCategoriesArray, function ($subCategory) use ($categoryVariantIds) {
            return in_array($subCategory->getId(), $categoryVariantIds, true);
        });

        $productObj->setSubCategory($subCategoriesArray);
    }
}
