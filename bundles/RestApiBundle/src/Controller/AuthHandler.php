<?php

namespace RestApiBundle\Controller;

use Pimcore\Db;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

use Pimcore\Model\User as PimcoreUser;
use Pimcore\Security\User\User as PimcoreSecurityUser;
use Pimcore\Security\Hasher\PimcoreUserPasswordHasher;


class AuthHandler
{
    private $db;

    public function __construct()
    {
        $this->db = Db::getConnection();
    }

    /**
     * @Route("/get-token", name="getToken", methods={"GET"})
     */
    public function getTokenAction(Request $request): JsonResponse
    {
        try {
            // $data = json_decode($request->getContent(), true);
            // $email = $data['email'];
            // $password = $data['password'];
            $sql = "SELECT name AS email, password FROM users WHERE type = 'user' AND name = :email";
            // $user = $this->db->fetchAssociative($sql, ['email' => $email]);

            $username = 'admin';
            $pimcoreUserModel = PimcoreUser::getByName($username);

            // Check if the user model exists and then wrap it with the Pimcore\Security\User\User class
            if ($pimcoreUserModel instanceof PimcoreUser) {
                // Wrap the user model with the Pimcore\Security\User\User class
                $pimcoreSecurityUser = new PimcoreSecurityUser($pimcoreUserModel);

                // Now you can access the methods and properties defined in the Pimcore\Security\User\User class
                // For example:
                $userId = $pimcoreSecurityUser->getId(); // Get user ID
                $userRoles = $pimcoreSecurityUser->getRoles(); // Get user roles
                // dd($pimcoreSecurityUser->getPassword());

                $passwordHasher = new PimcoreUserPasswordHasher();
                dd($passwordHasher->verify($pimcoreSecurityUser->getPassword(), 'admin'));


                // ... other operations with $pimcoreSecurityUser
            } else {
                // Handle the case when the user with the specified name doesn't exist
                // Example: Log an error, return an error response, etc.
            }

            return new JsonResponse([
                // 'email' => $pimcoreSecurityUser->getPassword(),
                'password' => null,
                'success' => true,
                'error' => null
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'token' => $e->getMessage(),
                'success' => false,
                'error' => 'There is an error in fetching token.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
