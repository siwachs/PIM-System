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
     * @param Concrete $object
     * @param CalculatedValue $context
     * @return float|string The computed actual price
     */
    public function compute(Concrete $object, CalculatedValue $context): string
    {
        $fieldName = $context->getFieldname();
        if ($fieldName == "actualPrice" || $fieldName === "price") {
            // $sellingPrice = $object->getSellingPrice();
            // $discount = $object->getDiscount();
            // $delivery = $object->getDeliveryCharges();
            // $tax = $object->getTax();
            // $discountAmount = $sellingPrice * ($discount / 100);
            // $sellingPrice -= $discountAmount;

            // return (float)($sellingPrice + $delivery + $tax);
            return 0;
        } else {
            //Logger
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

        // return $this->compute($object, $context);
        return 0;
    }
}
