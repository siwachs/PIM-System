<?php

namespace App\EventSubscriber;

use Pimcore\Db;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PreSaveEvent;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PostSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Data\Video;
use Pimcore\Model\DataObject\Product;



use Pimcore\Model\Notification\Service\NotificationService;

class DataImportSubscriber implements EventSubscriberInterface
{
    private $notificationService;
    private $sender;
    private $receiver;
    private $importerStartOn;
    private $totalObjects;
    private $objectHavingError;
    private $db;

    public function __construct(
        NotificationService $notificationService,
        int $sender,
        int $receiver
    ) {
        $this->notificationService = $notificationService;
        $this->sender = $sender;
        $this->receiver = $receiver;
        $this->totalObjects = 0;
        $this->objectHavingError = 0;
        $this->importerStartOn = date('Y-m-d H:i:s');
        $this->db = Db::getConnection();
    }

    public static function getSubscribedEvents()
    {
        return [
            PreSaveEvent::class => 'onPreSave',
            PostSaveEvent::class => 'onPostSave',
        ];
    }

    /**
     * Inserts video data into the object.
     *
     * @param $dataObject
     * @throws \Exception
     */
    private function insertVideo($dataObject)
    {
        try {
            $videoMeta = explode(',', $dataObject->getVideoMeta());
            $videoMeta = array_map('trim', $videoMeta);

            $assetVideoPath = trim($videoMeta[0] ?? '');
            $assetImagePath = trim($videoMeta[1] ?? '');

            $assetVideo = Asset::getByPath($assetVideoPath);
            $assetImage = Asset::getByPath($assetImagePath);
            $videoData = new Video();
            if ($assetVideo !== null) {
                $videoData->setData($assetVideo);
            }
            $videoData->setType("asset");
            if ($assetImage !== null) {
                $videoData->setPoster($assetImage);
            }
            $videoData->setTitle($videoMeta[2] ?? "");
            $videoData->setDescription($videoMeta[3] ?? "");

            if ($assetVideo !== null) {
                $dataObject->setVideo($videoData);
            }
        } catch (\Exception $e) {
            //Handle Error
        }
    }

    /**
     * Retrieves the size of the importer queue.
     *
     * @return mixed
     * @throws \Exception
     */
    private function getImporterQueueSize()
    {
        $sql = "SELECT COUNT(*) FROM bundle_data_hub_data_importer_queue";
        return $this->db->fetchOne($sql);
    }

    /**
     * Retrieves the logs of the importer queue.
     *
     * @return mixed
     * @throws \Exception
     */
    private function getLogs()
    {
        try {
            $sql = "SELECT
            application_logs.priority AS 'Priority',
            application_logs.message AS 'Message',
            application_logs.timestamp AS 'Occurred On'
        FROM
            application_logs
        WHERE
            application_logs.priority = 'error'
            AND application_logs.source LIKE '%DataImporterBundle%'
        ORDER BY
            application_logs.timestamp DESC";
            return $this->db->fetchAllAssociative($sql);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Executes actions before saving the object.
     *
     * @param PreSaveEvent $event
     * @throws \Exception
     */
    public function onPreSave(PreSaveEvent $event)
    {
        $dataObject = $event->getDataObject();
        $this->totalObjects++;
        if ($dataObject instanceof Product) {
            $this->insertVideo($dataObject);
        }
    }

    /**
     * Executes actions after saving the object.
     *
     * @param PostSaveEvent $event
     * @throws \Exception
     */
    public function onPostSave(PostSaveEvent $event)
    {
        try {
            if ($this->getImporterQueueSize() === 1) {
                $this->logError();
                $this->logSummary();
                $this->sendNotification();
            }
        } catch (\Exception $e) {
            // Handle Error.
        }
    }

    /**
     * Sends a notification.
     *
     * @throws \Exception
     */
    private function sendNotification()
    {
        $title = 'Notification';
        $message = 'All objects are imported.';

        $this->notificationService->sendToUser($this->receiver, $this->sender, $title, $message);
    }

    /**
     * Fetches and logs summary.
     *
     * @throws \Exception
     */
    private function logSummary()
    {
        try {
            $assetFilename = 'importer_summary.txt';
            $existingAsset = \Pimcore\Model\Asset::getByPath("/Logs/" . $assetFilename);
            $content = "";
            $content = "Total Objects: " . $this->totalObjects . "\n";
            $content .= "Objects Having Error: " . $this->objectHavingError . "\n";
            $content .= "Objects Saved Without Error: " . ($this->totalObjects - $this->objectHavingError) . "\n";

            if (!$existingAsset instanceof \Pimcore\Model\Asset) {
                $asset = new \Pimcore\Model\Asset();
                $asset->setFilename($assetFilename);
                $asset->setData($content);
                $asset->setParent(\Pimcore\Model\Asset::getByPath("/Logs"));
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
     * Fetches and logs error data to a CSV file.
     *
     * @throws \Exception
     */
    private function logError()
    {
        try {
            $logs = $this->getLogs();
            $csvContent = "Priority,Message,Occurred On\n";
            foreach ($logs as $row) {
                $priority = $row['Priority'];
                $message = '"' . $row['Message'] . '"';
                $unixTimestamp = \DateTime::createFromFormat('Y-m-d H:i:s', $row['Occurred On']);
                $occurredOn = $unixTimestamp->format('d/m/Y h:i A');

                if (strtotime($row['Occurred On']) >= strtotime($this->importerStartOn)) {
                    $this->objectHavingError++;
                }

                $csvContent .= "$priority,$message,$occurredOn\n";
            }

            $assetFilename = 'importer_logs.csv';
            $existingAsset = \Pimcore\Model\Asset::getByPath("/Logs/" . $assetFilename);

            if (!$existingAsset instanceof \Pimcore\Model\Asset) {
                $asset = new \Pimcore\Model\Asset();
                $asset->setFilename($assetFilename);
                $asset->setData($csvContent);
                $asset->setParent(\Pimcore\Model\Asset::getByPath("/Logs"));
                $asset->save();
            } else {
                $existingAsset->setData($csvContent);
                $existingAsset->save();
            }
        } catch (\Exception $e) {
            // Handle Error.
        }
    }
}
