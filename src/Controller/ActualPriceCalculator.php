<?php


namespace App\Controller;

use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\ClassDefinition\CalculatorClassInterface;
use Pimcore\Model\DataObject\Data\CalculatedValue;

class ActualPriceCalculator implements CalculatorClassInterface
{
    /**
     * Computes the actual price based on specific conditions.
     *
     * @param Concrete $product
     * @param CalculatedValue $context
     * @return float|string The computed actual price
     */
    public function compute(Concrete $product, CalculatedValue $context): string
    {
        if ($context->getFieldname() == "price") {

            $language = $context->getPosition();
            $sellingPriceLocalized = $product->getSellingPrice($language);
            $discountLocalized = $product->getDiscount($language);
            $deliveryLocalized = $product->getDeliveryCharges($language);
            $taxLocalized = $product->getTax($language);

            $discountAmount = $sellingPriceLocalized * ($discountLocalized / 100);
            $sellingPriceLocalized -= $discountAmount;

            return $sellingPriceLocalized + $deliveryLocalized + $taxLocalized;
        } else {
            // Logger
        }
    }

    /**
     * Retrieves the calculated value for editing mode.
     *
     * @param Concrete $object
     * @param CalculatedValue $context
     * @return string The computed value for editing mode
     */
    public function getCalculatedValueForEditMode(Concrete $object, CalculatedValue $context): string
    {

        return $this->compute($object, $context);
    }
}
