<?php

namespace EventsListenersBundle\EventListener;

use Pimcore\Event\Model\ElementEventInterface;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Event\Model\AssetEvent;
use Pimcore\Event\Model\DocumentEvent;

class TestListener
{
    public function onPreUpdate(ElementEventInterface $event): void
    {
        if ($event instanceof AssetEvent) {
            // do something with the asset
            $asset = $event->getAsset();
        } else if ($event instanceof DocumentEvent) {
            // do something with the document
            $document = $event->getDocument();
        } else if ($event instanceof DataObjectEvent) {
            // do something with the object
            $object = $event->getObject();
            // we don't have to call save here as we are in the pre-update event anyway ;-) 
        }
    }
}
