<?php

namespace App\EventListener;

use Pimcore\Event\Model\ElementEventInterface;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\Product;

class ObjectListener
{
    public function onObjectPreUpdate(ElementEventInterface $e): void
    {
        if ($e instanceof DataObjectEvent) {
            $object = $e->getObject();

            if ($object instanceof Product) {
                $subCategories = $object->getSubCategory();
                $filteredArray = array_filter($subCategories, function ($item) {
                    $path = $item->getPath();
                    $pathParts = explode('/', trim($path, '/'));
                    return count($pathParts) !== 1;
                });
                $object->setSubCategory($filteredArray);
            }
        }
    }
}
