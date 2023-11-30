<?php


namespace App\Controller;

use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\ClassDefinition\CalculatorClassInterface;
use Pimcore\Model\DataObject\Data\CalculatedValue;

class ActualPriceCalculator implements CalculatorClassInterface
{
    public function compute(Concrete $object, CalculatedValue $context): string
    {
        $fieldName = $context->getFieldname();
        if ($fieldName == "actualPrice" || $fieldName === "price") {
            $sellingPrice = $object->getSellingPrice();
            $discount = $object->getDiscount();
            $delivery = $object->getDeliveryCharges();
            $tax = $object->getTax();
            $discountAmount = $sellingPrice * ($discount / 100);
            $sellingPrice -= $discountAmount;

            return (float)($sellingPrice + $delivery + $tax);
        } else {
            //Logger
        }
    }

    public function getCalculatedValueForEditMode(Concrete $object, CalculatedValue $context): string
    {

        return $this->compute($object, $context);
    }
}
