<?php

namespace App\EventListener;

use Pimcore\Event\Model\ElementEventInterface;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\Product;

class ObjectListener
{
    /**
     * Filters subcategories of a Product before update.
     *
     * @param ElementEventInterface $e
     * @return void
     */
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


                $images = $object->getImages();
                if ($images instanceof \Pimcore\Model\DataObject\Data\ImageGallery) {
                    $imageArray = $images->getItems();
                    $filteredImages = array_filter($imageArray, function ($image) {
                        return $image !== null;
                    });
                    $filteredImages = array_slice($filteredImages, 0, 5);
                    $images->setItems($filteredImages);
                }
            }
        }
    }
}
