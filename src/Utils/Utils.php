<?php

namespace App\Utils;

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Data\Link;
use Pimcore\Model\Notification\Service\NotificationService;

class Utils
{
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

            // Get the header row values
            $columnIndex = 'A';
            while ($columnIndex <= $highestColumn) {
                $headerRow[] = $sheet->getCell($columnIndex . '1')->getValue();
                $columnIndex++;
            }

            // Read each row and create associative array with keys from header row
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [];
                $columnIndex = 'A';
                foreach ($headerRow as $header) {
                    $cellValue = $sheet->getCell($columnIndex . $row)->getValue();
                    $rowData[$header] = $cellValue;
                    $columnIndex++;
                }
                $data[] = $rowData; // Push each row as an associative array to the main array
            }

            return $data;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if founded year is valid (not greater than current year)
     *
     * @param int $yearFounded The year the brand was founded
     * @return bool Returns true if the year is valid (not greater than the current year), otherwise false
     */
    public static function isValidYearFounded($yearFounded): bool
    {
        $currentYear = date('Y');
        return $yearFounded <= $currentYear;
    }

    /**
     * Get Social Media Link as Pimcore Link object
     *
     * @param string $url The URL of the social media link
     * @return Link|null Returns a Link object or null if the URL is empty
     */
    public static function getSocialMediaLinkObject($url, $text, $title): ?Link
    {
        if (empty($url)) {
            return null;
        }

        $l = new Link();
        $l->setPath($url);
        $l->setText($text);
        $l->setTitle($title);
        return $l;
    }

    /**
     * logs summary.
     *
     * @throws \Exception
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
            $content .= "Completly Failed Objects: " . $completelyFailed . "\n";
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
            // Handle Error.
        }
    }

    /**
     * logs error.
     *
     * @throws \Exception
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
            // Handle Error.
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
