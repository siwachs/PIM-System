<?php

namespace RestApiBundle\Middleware;

use DateTimeImmutable;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Lcobucci\JWT\UnencryptedToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class TokenValidationMiddleware
{
    public function handleValidation(Request $request): ?JsonResponse
    {
        try {
            $authorizationHeader = $request->headers->get('Authorization');
            $tokenResponse = $this->validateToken($authorizationHeader);

            if ($tokenResponse !== null) {
                return $tokenResponse;
            }

            return null; // Token is valid.
        } catch (CannotDecodeContent | InvalidTokenStructure | UnsupportedHeaderFound | \Exception $e) {
            return $this->generateInternalErrorResponse($e->getMessage());
        }
    }

    private function validateToken(?string $authorizationHeader): ?JsonResponse
    {
        if (!$authorizationHeader || !preg_match('/Bearer\s(\S+)/', $authorizationHeader, $matches)) {
            return $this->generateUnauthorizedResponse('No token found.');
        }

        $jwt = $matches[1];
        $parser = new Parser(new JoseEncoder());
        $token = $parser->parse($jwt);

        assert($token instanceof UnencryptedToken);

        $username = $token->claims()->get('username');

        if (!in_array($username, ['admin', 'retailer', 'wholesaler']) || $token->isExpired(new DateTimeImmutable())) {
            return $this->generateUnauthorizedResponse('No valid token found.');
        }

        return null;
    }

    private function generateUnauthorizedResponse(string $errorMessage): JsonResponse
    {
        return new JsonResponse([
            'products' => [],
            'success' => false,
            'error' => $errorMessage
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function generateInternalErrorResponse(string $errorMessage): JsonResponse
    {
        return new JsonResponse([
            'products' => [],
            'success' => false,
            'error' => $errorMessage
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
