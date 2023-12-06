<?php

namespace RestApiBundle\Controller;

use Pimcore\Model\User;
use Pimcore\Tool\Authentication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class AuthHandler
{
    /**
     * @Route("/get-token", name="getToken", methods={"GET"})
     */
    public function getTokenAction(Request $request): JsonResponse
    {
        try {
            $credentials = json_decode($request->getContent(), true);
            $user = User::getByName("Mohit");
            $data = new Authentication();
            $res = $data->verifyPassword($user, "1234567s890");
        } catch (\Exception $e) {
            return new JsonResponse([
                'token' => null,
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
