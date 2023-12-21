<?php

namespace App\EventListener;

use Pimcore\Event\Model\ElementEventInterface;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\Category;

class ObjectListener
{
    /**
     * Filters subcategories of a Product before update.
     *
     * @param ElementEventInterface $event
     * @return void
     */
    public function onObjectPreUpdate(ElementEventInterface $event): void
    {
        if ($event instanceof DataObjectEvent) {
            $object = $event->getObject();

            if ($object instanceof Product) {
                $this->filterSubCategories($object);
                $this->filterImages($object);
                $this->calculateLocalizedPrice($object);
            }
        }
    }

    /**
     * Filters subcategories of a Product.
     * Subcategories cannot have parent categories.
     *
     * @param Product $product
     * @return void
     */
    private function filterSubCategories(Product $product): void
    {
        $subCategories = $product->getSubCategory();
        $filteredSubCategories = array_filter($subCategories, function ($item) {
            $path = $item->getPath();
            $pathParts = explode('/', trim($path, '/'));
            return count($pathParts) !== 1;
        });

        $product->setSubCategory($filteredSubCategories);
    }

    /**
     * Filters images of a Product.
     * Allows only up to 5 images.
     *
     * @param Product $product
     * @return void
     */
    private function filterImages(Product $product): void
    {
        $images = $product->getImages();
        if ($images instanceof \Pimcore\Model\DataObject\Data\ImageGallery) {
            $imageArray = $images->getItems();
            $filteredImages = array_filter($imageArray, function ($image) {
                return $image !== null;
            });

            // Allow only a maximum of 5 images
            $filteredImages = array_slice($filteredImages, 0, 5);
            $images->setItems($filteredImages);
        }
    }

    private function calculateLocalizedPrice(Product $product): void
    {
        // Fetch All available languages
        $languages = \Pimcore\Tool::getValidLanguages();
        foreach ($languages as $language) {
            $sellingPriceLocalized = $product->getSellingPrice($language);
            $discountLocalized = $product->getDiscount($language);
            $deliveryLocalized = $product->getDeliveryCharges($language);
            $taxLocalized = $product->getTax($language);

            $discountAmount = $sellingPriceLocalized * ($discountLocalized / 100);
            $sellingPriceLocalized -= $discountAmount;

            $product->setPrice($sellingPriceLocalized + $deliveryLocalized + $taxLocalized, $language);
        }
    }
}
