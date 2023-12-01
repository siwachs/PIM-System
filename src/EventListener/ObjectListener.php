<?php

namespace App\EventListener;

use Pimcore\Event\Model\ElementEventInterface;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Bundle\DataImporterBundle\Event\DataObject\PostSaveEvent;

class ObjectListener
{
    public function onObjectPreUpdate(ElementEventInterface $e): void
    {
        if ($e instanceof DataObjectEvent) {
            $product = $e->getObject();

            if ($product->getClassName() === 'Product') {
                $subCategories = $product->getSubCategory();
                $filteredArray = array_filter($subCategories, function ($item) {
                    $path = $item->getPath();
                    $pathParts = explode('/', trim($path, '/'));
                    return count($pathParts) !== 1;
                });
                $product->setSubCategory($filteredArray);
            }
        }
    }
}
