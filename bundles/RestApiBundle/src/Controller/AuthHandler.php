<?php

namespace RestApiBundle\Controller;

// JWT
use DateTimeImmutable;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\JwtFacade;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

use Pimcore\Model\User;
use Pimcore\Tool\Authentication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class AuthHandler
{
    private $tokenSecretKey;

    public function __construct(string $tokenSecretKey)
    {
        $this->tokenSecretKey = $tokenSecretKey;
    }

    /**
     *@param mixed $user
     * @return string|null
     */
    private function generateToken($user): ?string
    {
        try {
            if ($user instanceof User) {
                $key = InMemory::plainText($this->tokenSecretKey);

                $token = (new JwtFacade())->issue(
                    new Sha256(),
                    $key,
                    static fn (
                        Builder $builder,
                        DateTimeImmutable $issuedAt
                    ): Builder => $builder
                        ->issuedBy('admin')
                        ->permittedFor('http://localpimcore.com')
                        ->expiresAt($issuedAt->modify('+60 minutes'))
                        ->withClaim('username', $user->getUsername())
                );

                return $token->toString();
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @Route("/get-token", name="getToken", methods={"POST"})
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getTokenAction(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $username = $data['username'];
            $password = $data['password'];
            $user = User::getByName($username);

            $auth = new Authentication();
            $isValid = $auth->verifyPassword($user, $password);
            if (!$isValid) {
                return new JsonResponse([
                    'token' => null,
                    'success' => false,
                    'error' => 'Invalid username or password'
                ], Response::HTTP_UNAUTHORIZED);
            }

            return new JsonResponse([
                'token' => $this->generateToken($user),
                'success' => true,
                'error' => null
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'token' => null,
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
