<?php

namespace RestApiBundle\Controller;

use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class BrandController extends FrontendController
{
    /**
     * @Route("/import_brands", name="importBrands",methods={"GET"})
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function importBrands(Request $request): JsonResponse
    {
        try {
            $brandsCsvId = $request->query->get('brandsCsvId');
            $brandsFolderId = $request->query->get('brandsFolderId');

            if (!isset($brandsCsvId) || !isset($brandsFolderId)) {
                return new JsonResponse([
                    'error' => 'Invalid Paremeters'
                ], JsonResponse::HTTP_BAD_REQUEST); //400
            }

            return new JsonResponse([
                'message' => 'Success'
            ], JsonResponse::HTTP_OK); //200
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'A server error occured.'
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR); //500
        }
    }
}
