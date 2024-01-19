<?php

/**
 * Utils Class
 *
 * This utility class provides various methods for common tasks related to Pimcore, PhpSpreadsheet,
 * and general data manipulation. It includes functions for handling spreadsheet data, creating
 * or retrieving folders, managing assets, adding images to product galleries, validating values,
 * creating social media link objects, setting categories and sub-categories for products, logging
 * summaries and errors, uploading files to Pimcore assets, and sending notifications and emails.
 *
 * @package App\Utils
 * @category Utility
 * @author Shubham Siwach
 * @version 1.0
 */

namespace App\Utils;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Pimcore\Model\DataObject;
use Pimcore\Model\Asset;
use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\DataObject\Data\ImageGallery;
use Pimcore\Model\DataObject\Data\Hotspotimage;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Data\Link;
use Pimcore\Model\Notification\Service\NotificationService;

class Utils
{
    const DEFAULT_ASSET_PARENT_PATH = '/';

    // Constants for summary messages
    const SUMMARY_TOTAL_OBJECTS = 'Total Objects: ';
    const SUMMARY_PARTIAL_FAILED = 'Partial Failed Objects: ';
    const SUMMARY_COMPLETELY_FAILED = 'Completely Failed Objects: ';
    const SUMMARY_FULLY_SUCCESSFUL = 'Fully Successful Objects: ';

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
     * Validates if a value is a valid number, a valid year founded, or a valid price.
     *
     * @param mixed $value The value to be validated
     * @param string $type The type of validation to perform ('number', 'year', or 'price')
     *
     * @return bool Returns true if the value is valid according to the specified type; otherwise, false.
     */
    public static function validateNumber($value, string $type = 'number'): bool
    {
        switch ($type) {
            case 'year':
                $currentYear = date('Y');
                return is_numeric($value) && $value > 0 && $value <= $currentYear;

            case 'price':
                return is_numeric($value) && floatval($value) > 0;

            default:
                return is_numeric($value) && floatval($value) > -1;
        }
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
     * Retrieves the object for a given class by its path if it exists.
     *
     * @param string $className The name of the class
     * @param string $path The path of the object
     *
     * @return mixed|null The object if found, otherwise null.
     */
    public static function getObjectByPathIfExists(string $className, string $path)
    {
        try {
            $obj = $className::getByPath($path);
            return $obj instanceof $className ? $obj : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Sets the main category and its sub-categories for a product.
     *
     * @param Product $productObj         The Product object to which categories are associated.
     * @param Category $category          The main category for the product.
     * @param string $productCategory     The product's category.
     * @param string $subCategoriesString A comma-separated string of sub-categories.
     *
     * @return void
     */
    public static function setCategoryAndSubCategories(
        Product $productObj,
        Category $category,
        $productCategory,
        $subCategoriesString
    ): void {
        // Set the main category
        $productObj->setCategory([$category]);

        // Extract sub-categories from the provided string
        $subCategories = array_map('trim', explode(',', $subCategoriesString));

        // Retrieve sub-categories by path and associate them with the product
        $subCategoriesArray = [];
        foreach ($subCategories as $subCategory) {
            $subCategoryObj = Category::getByPath('/Categories/' . $productCategory . '/' . $subCategory);
            if ($subCategoryObj !== null) {
                $subCategoriesArray[] = $subCategoryObj;
            }
        }

        // Retrieve variant IDs of the main category
        $categoryVariants = $category->getChildren([DataObject::OBJECT_TYPE_VARIANT]);
        $categoryVariantIds = array_map(fn ($variant) => $variant->getId(), iterator_to_array($categoryVariants));

        // Filter and set only those sub-categories which are variants of the main category
        $subCategoriesArray = array_filter($subCategoriesArray, function ($subCategory) use ($categoryVariantIds) {
            return in_array($subCategory->getId(), $categoryVariantIds, true);
        });

        // Set sub-categories for the product
        $productObj->setSubCategory($subCategoriesArray);
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
            $content .= self::SUMMARY_TOTAL_OBJECTS . $totalObjects . "\n";
            $content .= self::SUMMARY_PARTIAL_FAILED . $partialFailed . "\n";
            $content .= self::SUMMARY_COMPLETELY_FAILED . $completelyFailed . "\n";
            $content .= self::SUMMARY_FULLY_SUCCESSFUL . $fullySuccessful;

            if (!$existingAsset instanceof \Pimcore\Model\Asset) {
                $asset = new \Pimcore\Model\Asset();
                $asset->setFilename($assetFilename);
                $asset->setData($content);
                $asset->setParent(\Pimcore\Model\Asset::getByPath($parentIdPath) ??
                    \Pimcore\Model\Asset::getByPath(self::DEFAULT_ASSET_PARENT_PATH));
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
                    \Pimcore\Model\Asset::getByPath(self::DEFAULT_ASSET_PARENT_PATH));
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
     * Uploads a file to Pimcore assets.
     *
     * @param string $assetFilename   The filename for the asset.
     * @param string $assetFilePath   The file path for the asset.
     * @param string $parentIdPath    The path of the parent directory for the asset.
     * @param string $localPath       The local file path to upload.
     *
     * @throws \Exception If there's an issue while handling the asset or its data.
     */
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
                ?? \Pimcore\Model\Asset::getByPath(self::DEFAULT_ASSET_PARENT_PATH));
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
