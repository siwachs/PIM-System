<?php

namespace RestApiBundle\Controller;

use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class ProductController extends FrontendController
{
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
                'sku' => $product->getSKU(),
                'name' => $product->getName($lang),
                'description' => $product->getDescription($lang),
                'country' => $product->getCountry(),
                'brand' => $product->getBrand()[0]->getKey(),
                'manufacturer' => $product->getManufacturer()[0]->getKey(),
                'category' => $product->getCategory()[0]->getKey(),
                'subCategories' => $subCategoriesString,
                'countryOfOrigin' => $product->getCountryOfOrigin(),
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
    public function getProductsAction(Request $request): JsonResponse
    {
        $offset = $request->query->get('offset', 0);
        $limit = $request->query->get('limit', 25);

        $orderBy = $request->query->get('orderBy', 'creationDate');
        $order = $request->query->get('order', 'ASC');

        $findBy = $request->query->get('findBy', null);
        $find = $request->query->get('find', null);

        $lang = $request->query->get('lang', 'en');

        try {
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
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'products' => [],
                'success' => false,
                'error' => 'There is a error in product listing.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
