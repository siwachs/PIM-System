<?php

namespace App\EventListener;

use Pimcore\Event\Model\ElementEventInterface;
use Pimcore\Model\DataObject;
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
        $category = $product->getCategory();
        if (empty($category)) {
            $product->setSubCategory([]);
            return;
        }

        $categoryVariants = $category[0]->getChildren([DataObject::OBJECT_TYPE_VARIANT]);
        $categoryVariantIds = array_map(fn ($variant) => $variant->getId(), iterator_to_array($categoryVariants));

        $subCategoriesArray = $product->getSubCategory();
        $subCategoriesArray = array_filter($subCategoriesArray, function ($subCategory) use ($categoryVariantIds) {
            return in_array($subCategory->getId(), $categoryVariantIds, true);
        });

        $product->setSubCategory($subCategoriesArray);
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
}
