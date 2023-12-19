<?php

namespace App\EventSubscriber;

use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PreSaveEvent;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PostSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DataImportSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            PreSaveEvent::class => 'onPreSave',
            PostSaveEvent::class => 'onPostSave',
        ];
    }


    /**
     * Executes actions before saving the object.
     *
     * @param PreSaveEvent $event
     * @throws \Exception
     */
    public function onPreSave(PreSaveEvent $event)
    {
    }

    /**
     * Executes actions after saving the object.
     *
     * @param PostSaveEvent $event
     * @throws \Exception
     */
    public function onPostSave(PostSaveEvent $event)
    {
    }
}
