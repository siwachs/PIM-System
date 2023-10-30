<?php

namespace App\Controller;

use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class CategoryController extends FrontendController
{
    /**
     * @Route("/get-category/{id}", name="getCategory",methods={"GET"})
     * @param Request $request
     * @param int $id
     *
     * @return Response
     */
    public function getCategoryByIdAction(int $id): Response
    {
        try {
            $categoryObject = DataObject\Category::getById($id);
            if (!$categoryObject) {
                return $this->render('error.html.twig', [
                    'error' => 'No category found.',
                ]);
            }

            return $this->render('category/category.html.twig', [
                'id' => $id,
                'name' => $categoryObject->getName(),
                'description' => $categoryObject->getDescription(),
            ]);
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * @Route("/get-categories", name="getCategories",methods={"GET"})
     * @param Request $request
     *
     * @return Response
     */
    public function getCategoriesAction(): Response
    {
        try {
            $categories = new DataObject\Category\Listing();

            return $this->render('/category/categories.html.twig', ['categories' => $categories]);
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * @Route("/create-category", name="createCategory",methods={"POST"})
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function createCategoryAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? null;
        $description = $data['description'] ?? null;
        $parentId = $data['parentId'] ?? null; //3
        $versionNote = $data['versionNote'] ?? null;

        if (!$name || !$description || !$parentId || !$versionNote) {
            return $this->json([
                'error' => 'Some of required fields are missing.'
            ], 400);
        }


        try {
            $newCategory = new DataObject\Category();
            $newCategory->setKey(\Pimcore\Model\Element\Service::getValidKey($name, 'object'));
            $newCategory->setParentId($parentId);
            $newCategory->setName($name);
            $newCategory->setDescription($description);
            $newCategory->save(["versionNote" => $versionNote]);
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
     * @Route("/update-category", name="updateCategory",methods={"PUT"})
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function updateCategoryAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? null;
        $description = $data['description'] ?? null;
        $categoryId = $data['categoryId'] ?? null;

        if (!$categoryId) {
            return $this->json([
                'error' => 'Category Id is missing.'
            ], 400);
        }

        try {
            $categoryObject = DataObject\Category::getById($categoryId);
            if (!$categoryObject) {
                return $this->json([
                    'error' => 'No category found.'
                ], 404);
            }

            if ($name) {
                $categoryObject->setName($name);
            }

            if ($description) {
                $categoryObject->setDescription($description);
            }
            $categoryObject->save();
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
     * @Route("/delete-category/{id}", name = "deleteCategory",methods={"DELETE"})
     * @return JsonResponse
     */
    public function  deleteCategoryAction(int $id): JsonResponse
    {
        try {
            $categoryObject = DataObject\Category::getById($id);
            if (!$categoryObject) {
                return $this->json([
                    'error' => 'No category found.'
                ], 404);
            }

            $categoryObject->delete();
            return $this->json([
                'success' => true
            ], 204); //Put request success but not content to send.
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
