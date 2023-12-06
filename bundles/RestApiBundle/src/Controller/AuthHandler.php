<?php

namespace RestApiBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;


class AuthHandler
{
    public function __construct()
    {
    }

    /**
     * @Route("/get-token", name="getToken", methods={"GET"})
     */
    public function getTokenAction(Request $request): JsonResponse
    {
        try {
        } catch (\Exception $e) {
            return new JsonResponse([
                'token' => null,
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
