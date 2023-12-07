<?php

namespace RestApiBundle\Controller;

use RestApiBundle\Middleware\TokenValidationMiddleware;
use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class ProductController extends FrontendController
{
    /**
     * @param array|null $array
     * @return string|null
     */
    private function getFirstKeyOrNull(?array $array): ?string
    {
        if (!empty($array)) {
            return $array[0]->getKey();
        }

        return null;
    }

    /**
     * @param array $products
     * @param string $lang
     *
     * @return array
     */
    private function listToAssoc($products, $lang)
    {
        $productData = [];
        foreach ($products as $product) {
            $subCategories = array_map(function ($category) {
                return $category->getKey();
            }, $product->getSubCategory());

            $subCategoriesString = implode(', ', $subCategories);

            $productData[] = [
                'id' => $product->getId(),
                'baseData' => [
                    'sku' => $product->getSKU(),
                    'name' => $product->getName($lang),
                    'description' => $product->getDescription($lang),
                    'country' => $product->getCountry(),
                    'brand' => $this->getFirstKeyOrNull($product->getBrand()),
                    'manufacturer' => $this->getFirstKeyOrNull($product->getManufacturer()),
                    'category' => $this->getFirstKeyOrNull($product->getCategory()),
                    'subCategories' => $subCategoriesString,
                    'price' => $product->getPrice(),
                    'color' => $product->getColor(),
                ],
                'assets' => [
                    'masterImage' => $product->getMasterImage() !== null ? $product->getMasterImage()->getPath() : null,
                    'video' => $product->getVideo() !== null ? $product->getVideo()->getData()->getPath() : null,
                ],
                'salesAndPricing' => [
                    'quantitySold' => $product->getQuantitySold(),
                    'revenue' => $product->getRevenue(),
                    'productAvailability' => $product->getProductAvailability(),
                    'rating' => $product->getRating(),
                    'basePrice' => $product->getBasePrice(),
                    'sellingPrice' => $product->getBasePrice(),
                    'deliveryCharges' => $product->getDeliveryCharges(),
                    'tax' => $product->getTax(),
                    'dicount' => $product->getDiscount() . " %",
                    'calculatedPrice' => $product->getActualPrice()
                ],
                'measurements' => [
                    'dimensions' => $product->getDimensions(),
                    'size' => $product->getSize(),
                    'weight' => $product->getWeight() . ' g'
                ],
                'technicalDetails' => [
                    'modelNumber' => $product->getModelNumber(),
                    'modelYear' => $product->getModelYear(),
                    'modelName' => $product->getModelName(),
                    'hardwareinterface' => $product->getHardwareInterface(),
                    'countryOfOrigin' => $product->getCountryOfOrigin(),
                ],
                'advanceTechnicalDetails' => [
                    'motherboard' => $this->getFirstKeyOrNull($product->getMotherboard()),
                    'OS' => $this->getFirstKeyOrNull($product->getOperatingSystem()),
                    'processor' => $this->getFirstKeyOrNull($product->getProcessor()),
                    'ram' => $this->getFirstKeyOrNull($product->getRam()),
                    'rom' => $this->getFirstKeyOrNull($product->getRom()),
                    'vram' => $this->getFirstKeyOrNull($product->getVram())
                ],
                'fullPath' => $product->getFullPath(),
                'createdAt' => date('h:i A, d F Y', $product->getCreationDate())
            ];
        }

        return $productData;
    }

    /**
     * @Route("/get-products", name="getProducts",methods={"GET"})
     * @param Request $request
     *
     * @return Response
     */
    public function getProductsAction(Request $request, TokenValidationMiddleware $tokenValidation): JsonResponse
    {
        $offset = $request->query->get('offset', 0);
        $limit = $request->query->get('limit', 25);

        $orderBy = $request->query->get('orderBy', 'creationDate');
        $order = $request->query->get('order', 'ASC');

        $findBy = $request->query->get('findBy', null);
        $find = $request->query->get('find', null);

        $lang = $request->query->get('lang', 'en');

        try {
            $response = $tokenValidation->handleValidation($request);
            if ($response !== null) {
                return $response;
            }

            $products = new DataObject\Product\Listing;
            $products->setLocale($lang);

            if (is_numeric($offset)) {
                $products->setOffset((int)$offset);
            }

            if (is_numeric($limit)) {
                $products->setLimit((int)$limit);
            }

            if (isset($orderBy) && isset($order)) {
                $products->setOrderKey($orderBy);
                $products->setOrder($order);
            }

            if ($findBy !== null && in_array($findBy, ['sku', 'name']) && $find !== null) {
                if ($findBy === 'sku') {
                    $products->setCondition("sku LIKE ?", ["%" . $find . "%"]);
                } elseif ($findBy === 'name') {
                    $products->setCondition("name LIKE ?", ["%" . $find . "%"]);
                }
            }

            return new JsonResponse([
                'products' => $this->listToAssoc($products, $lang),
                'success' => true,
                'error' => null
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'products' => [],
                'success' => false,
                'error' => 'Error in get products.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
