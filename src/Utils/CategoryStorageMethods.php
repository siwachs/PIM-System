<?php

namespace App\Utils;

use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject;

class CategoryStorageMethods
{
    const OBJECT_NAME = 'Object Name';
    const ROOT_PATH = '/Categories/';

    // Properties for tracking import status
    private static $totalObjects = 0;
    private static $partialFailed = 0;
    private static $completelyFailed = 0;
    private static $fullySuccessful = 0;
    private static $errorLog = "";

    // Constants for Categories log summary
    const CATEGORIES_ASSET_FILENAME = "Categories Import Summary.txt";
    const CATEGORIES_ASSET_FILE_PATH = "/Logs/Categories/Categories Import Summary.txt";
    const CATEGORIES_PARENT_DIRECTORY_PATH = "/Logs/Categories";

    // Constants for Categories error log
    const CATEGORIES_ERROR_ASSET_FILENAME = "Categories Error Report.txt";
    const CATEGORIES_ERROR_ASSET_FILE_PATH = "/Logs/Categories/Categories Error Report.txt";
    const CATEGORIES_ERROR_PARENT_DIRECTORY_PATH = "/Logs/Categories";

    private static function mapData($category, $categoryObj, $countryCode)
    {
        $categoryObj->setName($category['Name'], $countryCode);
        $categoryObj->setDescription($category['Description'], $countryCode);
        $categoryObj->setSlug($category['Slug'], $countryCode);
        $categoryObj->setKeywords($category['Keywords'], $countryCode);

        self::$fullySuccessful++;
    }

    /**
     * Store Categories
     *
     * @param array $categoryArray An array containing category data
     */
    public static function storeCategories($categoryArray, $countryCode)
    {
        self::$totalObjects = count($categoryArray);
        usort($categoryArray, function ($a, $b) {
            $isEmptyParentA = empty($a['Parent']);
            $isEmptyParentB = empty($b['Parent']);

            // Compare the empty parent conditions
            if ($isEmptyParentA !== $isEmptyParentB) {
                return $isEmptyParentA ? -1 : 1;
            }

            // Both elements have the same empty/non-empty parent status
            return 0;
        });

        foreach ($categoryArray as $category) {
            try {
                $categoryName = $category[self::OBJECT_NAME];
                if (empty($category['Name'])) {
                    self::$completelyFailed++;
                    self::$errorLog .= "Error in " . $categoryName . ". The name field is empty.\n";
                    continue;
                }

                $categoryObj = self::fetchCategory($category);
                if ($categoryObj instanceof Category) {
                    self::updateCategory($category, $categoryObj, $countryCode);
                } else {
                    self::createCategory($category, $countryCode);
                }
            } catch (\Exception $e) {
                dump($e->getMessage());
            }
        }

        self::logCategorySummary();
    }

    private static function fetchCategory($category)
    {
        $categoryName = $category[self::OBJECT_NAME];
        $parent = $category['Parent'];
        $categoryPath = empty($parent) ?
            self::ROOT_PATH . $categoryName : self::ROOT_PATH .
            $parent . '/' . $categoryName;
        return Category::getByPath($categoryPath);
    }

    private static function updateCategory($category, $categoryObj, $countryCode)
    {
        $categoryObj->setPublished(false);
        self::mapData($category, $categoryObj, $countryCode);
        $categoryObj->save();
    }

    private static function createCategory($category, $countryCode)
    {
        $categoryName = $category[self::OBJECT_NAME];
        $parent = $category['Parent'];

        $newCategory = new Category();
        if (empty($parent)) {
            $newCategory->setKey(\Pimcore\Model\Element\Service::getValidKey($categoryName, 'object'));
            $parentId = Utils::getOrCreateFolderIdByPath("/Categories", 1);
            $newCategory->setParentId($parentId);
        } else {
            $rootPath = self::ROOT_PATH . $parent;
            $rootCategoryObj = Category::getByPath($rootPath);

            if ($rootCategoryObj instanceof Category) {
                $newCategory->setParent($rootCategoryObj);
                $newCategory->setKey(\Pimcore\Model\Element\Service::getValidKey($categoryName, 'object'));
                $newCategory->setType(DataObject::OBJECT_TYPE_VARIANT);
            } else {
                self::$completelyFailed++;
                self::$errorLog .= "Error in " . $categoryName . ". The parent category is missing.\n";
                return;
            }
        }

        self::mapData($category, $newCategory, $countryCode);
        $newCategory->save();
    }

    private static function logCategorySummary()
    {
        Utils::logSummary(
            self::CATEGORIES_ASSET_FILENAME,
            self::CATEGORIES_ASSET_FILE_PATH,
            self::CATEGORIES_PARENT_DIRECTORY_PATH,
            self::$totalObjects,
            self::$partialFailed,
            self::$completelyFailed,
            self::$fullySuccessful
        );

        Utils::logError(
            self::CATEGORIES_ERROR_ASSET_FILENAME,
            self::CATEGORIES_ERROR_ASSET_FILE_PATH,
            self::CATEGORIES_ERROR_PARENT_DIRECTORY_PATH,
            self::$errorLog
        );
    }
}
