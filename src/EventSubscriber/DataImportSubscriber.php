<?php

namespace App\EventSubscriber;

use Pimcore;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PreSaveEvent;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PostSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Pimcore\Model\Asset;
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
        // For extra use.
    }

    public function onPostSave(PostSaveEvent $event)
    {
        $dataObject = $event->getDataObject();

        $this->sendNotification($dataObject);
    }

    private function sendNotification($dataObject)
    {
        $title = 'Using Class ' . $dataObject->getClassName();
        $message = 'Data Importing finished.';

        $this->notificationService->sendToUser($this->receiver, $this->sender, $title, $message, $dataObject);
    }
}
