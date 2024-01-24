<?php

namespace App\EventListener;

use Pimcore\Event\Model\ElementEventInterface;
use Pimcore\Model\Element\ValidationException;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Product;

class ObjectListener
{
    /**
     * onObjectPreUpdate trigger before updating product.
     *
     * @param ElementEventInterface $event
     * @return void
     */
    public function onObjectPreUpdate(ElementEventInterface $event): void
    {
        if ($event instanceof DataObjectEvent) {
            $object = $event->getObject();

            if ($object instanceof Product) {
                $this->validateCategory($object);
                $this->filterImages($object);
                $this->errorLogger($object);
            }
        }
    }

    /**
     * onObjectPreDelete trigger before deleting category.
     *
     * @param ElementEventInterface $event
     * @return void
     */
    public function onObjectPreDelete(ElementEventInterface $event): void
    {
        if ($event instanceof DataObjectEvent) {
            $object = $event->getObject();

            if ($object instanceof Category) {
                $products = $object->getProducts();
                foreach ($products as $product) {
                    $product->setCategory([]);
                    $product->setPublished(false);
                    $product->save();
                }
            }
        }
    }

    /**
     * Validates the category of a product.
     *
     * @param Product $product The product to validate.
     *
     * @return void
     */
    public function validateCategory(Product $product): void
    {
        $category = $product->getCategory();
        if (empty($category)) {
            return;
        }
        $categoryPathArray = explode('/', $category[0]->getFullPath());
        $categoryPathArray = array_filter($categoryPathArray);
        if (count($categoryPathArray) <= 2) {
            throw new ValidationException('Only child category allowed.');
        }
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
        $languages = \Pimcore\Tool::getValidLanguages();
        foreach ($languages as $language) {
            $images = $product->getImages($language);
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

    /**
     * Logs errors based on product type and attributes.
     *
     * @param Product $product The Product object to be checked
     * @throws ValidationException Throws a ValidationException for invalid product types or attributes
     */
    private function errorLogger(Product $product): void
    {
        $this->checkObjectType($product);
        $this->checkVariantType($product);
    }

    /**
     * Checks if the product type is 'object' and validates its attributes.
     *
     * @param Product $product The Product object to be checked
     * @throws ValidationException Throws a ValidationException for 'object' type with invalid attributes
     */
    private function checkObjectType(Product $product): void
    {
        if ($product->getType() === 'object') {
            $this->validateObjectAttributes($product);
        }
    }

    /**
     * Validates attributes for 'object' type products.
     *
     * @param Product $product The Product object to be checked
     * @throws ValidationException Throws a ValidationException for invalid attributes of 'object' type
     */
    private function validateObjectAttributes(Product $product): void
    {
        $sku = $product->getSku();
        if (!empty($sku) || $sku !== null) {
            throw new ValidationException("Object type cannot have SKU.");
        }

        $languages = \Pimcore\Tool::getValidLanguages();
        foreach ($languages as $language) {
            $this->validateObjectLanguageAttributes($product, $language);
        }
    }

    /**
     * Validates language attributes for 'object' type products.
     *
     * @param Product $product The Product object to be checked
     * @param string $language The language to be validated
     * @throws ValidationException Throws a ValidationException for invalid language attributes of 'object' type
     */
    private function validateObjectLanguageAttributes(Product $product, string $language): void
    {
        $basePrice = $product->getBasePrice($language);
        if (!empty($basePrice) || $basePrice !== null) {
            throw new ValidationException("Object type cannot have base price.");
        }

        $sellingPrice = $product->getSellingPrice($language);
        if (!empty($sellingPrice) || $sellingPrice !== null) {
            throw new ValidationException("Object type cannot have selling price.");
        }
    }

    /**
     * Checks if the product type is 'variant' and validates its attributes.
     *
     * @param Product $product The Product object to be checked
     * @throws ValidationException Throws a ValidationException for 'variant' type with invalid attributes
     */
    private function checkVariantType(Product $product): void
    {
        if ($product->getType() === 'variant') {
            $this->validateVariantLanguageAttributes($product);
        }
    }

    /**
     * Validates language attributes for 'variant' type products.
     *
     * @param Product $product The Product object to be checked
     * @throws ValidationException Throws a ValidationException for invalid language attributes of 'variant' type
     */
    private function validateVariantLanguageAttributes(Product $product): void
    {
        $sku = $product->getSku();
        if (empty($sku) || $sku === null) {
            throw new ValidationException("Variant type cannot have empty SKU.");
        }
    }
}
