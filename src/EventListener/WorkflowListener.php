<?php

namespace App\EventListener;

use Symfony\Component\Workflow\Event\TransitionEvent;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\Element\ValidationException;

class WorkflowListener
{
    private function validateName(Product $product): bool
    {
        $languages = \Pimcore\Tool::getValidLanguages();
        foreach ($languages as $language) {
            if (empty($product->getName($language))) {
                return false;
            }
        }

        return true;
    }

    private function validateMasterImage(Product $product): bool
    {
        $languages = \Pimcore\Tool::getValidLanguages();
        foreach ($languages as $language) {
            if (empty($product->getMasterImage($language))) {
                return false;
            }
        }

        return true;
    }

    public function onTransitionToEnrichment(TransitionEvent $event): void
    {
        $errors = [];

        $object = $event->getSubject();
        if ($object instanceof Product && $object->getType() === 'variant') {
            $isValidName = $this->validateName($object);
            $brand = $object->getBrand();
            $manufacturer = $object->getManufacturer();
            $category = $object->getCategory();

            if (!$isValidName) {
                $errors[] = 'Cannot allow empty name.';
            }

            if (empty($brand)) {
                $errors[] = 'Cannot allow empty brand.';
            }

            if (empty($manufacturer)) {
                $errors[] = 'Cannot allow empty manufacturer.';
            }

            if (empty($category)) {
                $errors[] = 'Cannot allow empty category.';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException(implode(' ', $errors));
        }
    }

    public function onTransitionToTechnicalities(TransitionEvent $event): void
    {
        $errors = [];

        $object = $event->getSubject();
        if ($object instanceof Product && $object->getType() === 'variant') {
            $isValidMasterImage = $this->validateMasterImage($object);
            if (!$isValidMasterImage) {
                $errors[] = 'Cannot allow empty master image.';
            }

            $languages = \Pimcore\Tool::getValidLanguages();
            foreach ($languages as $language) {
                $basePrice = $object->getBasePrice($language);
                $sellingPrice = $object->getSellingPrice($language);

                if (empty($basePrice) || $basePrice === null || empty($sellingPrice) || $sellingPrice === null) {
                    $errors[] = 'Cannot allow empty base price and selling price.';
                    break;
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException(implode(' ', $errors));
        }
    }
}
