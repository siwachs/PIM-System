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
    const NO_PRODUCT_FOUND_ERROR = 'No product found.';

    /**
     * @Route("/get-product/{id}", name="getProduct",methods={"GET"})
     * @param Request $request
     * @param int $id
     *
     * @return Response
     */
    public function getProductByIdAction(int $id): Response
    {
        try {
            $productObject = DataObject\Product::getById($id);
            if (!$productObject) {
                return $this->render('error.html.twig', [
                    'error' => self::NO_PRODUCT_FOUND_ERROR,
                ]);
            }

            return $this->render('product/product.html.twig', [
                'id' => $id,
                'name' => $productObject->getName(),
                'description' => $productObject->getDescription(),
                'stockAvailability' => $productObject->getStockAvailability(),
                'size' => $productObject->getSize(),
                'categories' => $productObject->getCategories()
            ]);
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * @Route("/get-products", name="getProducts",methods={"GET"})
     * @param Request $request
     *
     * @return Response
     */
    public function getProductsAction(): Response
    {
        try {
            $products = new DataObject\Product\Listing();

            return $this->render('/product/products.html.twig', ['products' => $products]);
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * @Route("/create-product", name="createProduct",methods={"POST"})
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function createProductAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? null;
        $description = $data['description'] ?? null;
        $parentId = $data['parentId'] ?? null; //2
        $versionNote = $data['versionNote'] ?? null;
        $stockAvailability = $data['stockAvailability'] ?? null;
        $size = $data['size'] ?? null;
        $categories = $data['categories'] ?? null;

        if (!$name || !$description || !$parentId || !$versionNote || !$stockAvailability || !$size || !$categories) {
            return $this->json([
                'error' => 'Some of required fields are missing.'
            ], 400);
        }


        try {
            $newProduct = new DataObject\Product();
            $newProduct->setKey(\Pimcore\Model\Element\Service::getValidKey($name, 'object'));
            $newProduct->setParentId($parentId); //2
            $newProduct->setName($name);
            $newProduct->setDescription($description);
            $newProduct->setStockAvailability($stockAvailability);
            $newProduct->setSize($size);

            $categoriesArray = [];
            foreach ($categories as $category) {
                $categoryObject = DataObject\Category::getById($category);
                if ($categoryObject) {
                    $categoriesArray[] = $categoryObject;
                }
            }
            $newProduct->setCategories($categoriesArray);

            $newProduct->save(["versionNote" => $versionNote]);
            return $this->json([
                'success' => true
            ], 201);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @Route("/update-product", name="updateProduct",methods={"PUT"})
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function updateProductAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? null;
        $description = $data['description'] ?? null;
        $productId = $data['productId'] ?? null; //2
        $stockAvailability = $data['stockAvailability'] ?? null;
        $size = $data['size'] ?? null;
        $categories = $data['categories'] ?? null;

        try {
            $productObject = DataObject\Product::getById($productId);
            if (!$productObject) {
                return $this->json([
                    'error' => self::NO_PRODUCT_FOUND_ERROR
                ], 404);
            }

            if ($name) {
                $productObject->setName($name);
            }

            if ($description) {
                $productObject->setDescription($description);
            }

            if ($stockAvailability) {
                $productObject->setStockAvailability($stockAvailability);
            }

            if ($size) {
                $productObject->setSize($size);
            }

            if ($categories) {
                $categoriesArray = [];
                foreach ($categories as $category) {
                    $categoryObject = DataObject\Category::getById($category);
                    if ($categoryObject) {
                        $categoriesArray[] = $categoryObject;
                    }
                }

                $productObject->setCategories($categoriesArray);
            }

            $productObject->save();
            return $this->json([
                'success' => true
            ], 204); //Put request success but not content to send.
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * @Route("/delete-product/{id}", name = "deleteProduct",methods={"DELETE"})
     * @return JsonResponse
     */
    public function  deleteProductAction(int $id): JsonResponse
    {
        try {
            $productObject = DataObject\Product::getById($id);
            if (!$productObject) {
                return $this->json([
                    'error' => self::NO_PRODUCT_FOUND_ERROR
                ], 404);
            }

            $productObject->delete();
            return $this->json([
                'success' => true
            ], 204); //Delete request success but not content to send.
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
