<?php

namespace App\EventSubscriber;

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

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        $this->sender = 2;
        $this->receiver = 13;
    }

    public static function getSubscribedEvents()
    {
        return [
            PreSaveEvent::class => 'onPreSave',
            PostSaveEvent::class => 'onPostSave',
        ];
    }

    public function onPreSave(PreSaveEvent $event)
    {
        $dataObject = $event->getDataObject();
        if ($dataObject instanceof Product) {
            $videoMeta = explode(',', $dataObject->getVideoMeta());
            $videoMeta = array_map('trim', $videoMeta);

            $assetVideo = Asset::getByPath(trim($videoMeta[0]) ?? "");
            $assetImage = Asset::getByPath(trim($videoMeta[1]) ?? "");

            $videoData = new Video();
            $videoData->setData($assetVideo);
            $videoData->setType("asset");
            $videoData->setPoster($assetImage);
            $videoData->setTitle($videoMeta[2] ?? "");
            $videoData->setDescription($videoMeta[3] ?? "");

            $dataObject->setVideo($videoData);
        }
    }

    public function onPostSave(PostSaveEvent $event)
    {
        $dataObject = $event->getDataObject();

        $this->sendNotification($dataObject);
    }

    private function sendNotification($dataObject)
    {
        $title = 'Using Class ' . $dataObject->getClassName();
        $message = 'Data Imported.';

        $this->notificationService->sendToUser($this->receiver, $this->sender, $title, $message, $dataObject);
    }
}
