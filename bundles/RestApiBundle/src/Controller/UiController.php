<?php

namespace RestApiBundle\Controller;

use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class UiController extends FrontendController
{
    /**
     * @Route("/file_imports", name="fileImports",methods={"GET"})
     *
     * @return JsonResponse
     */
    public function fileImports(): Response
    {
        return $this->render('file_imports.html.twig');
    }
}
