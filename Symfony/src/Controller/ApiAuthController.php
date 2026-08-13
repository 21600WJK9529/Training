<?php

namespace App\Controller;

use App\Service\ApiJwtTokenService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth', name: 'api_auth_')]
final class ApiAuthController extends AbstractController
{
    public function __construct(
        private readonly string $apiAuthUsername,
        private readonly string $apiAuthPassword
    ) {
    }

    #[Route('/token', name: 'token', methods: ['POST'])]
    public function token(Request $request, ApiJwtTokenService $tokenService, LoggerInterface $logger): JsonResponse
    {
        try {
            $payload = json_decode((string) $request->getContent(), true);

            if (!is_array($payload)) {
                return new JsonResponse([
                    'message' => 'Invalid JSON payload.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $username = isset($payload['username']) && is_string($payload['username']) ? trim($payload['username']) : '';
            $password = isset($payload['password']) && is_string($payload['password']) ? $payload['password'] : '';

            if ($username === '' || $password === '') {
                return new JsonResponse([
                    'message' => 'The username and password fields are required.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($username !== $this->apiAuthUsername || $password !== $this->apiAuthPassword) {
                return new JsonResponse([
                    'message' => 'Invalid credentials.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            return new JsonResponse([
                'token' => $tokenService->createToken($username, ['ROLE_API_USER']),
            ], Response::HTTP_OK);
        } catch (\Throwable $exception) {
            $logger->error('[API AUTH - TOKEN ISSUE FAILED]', [
                'error' => $exception->getMessage(),
            ]);

            return new JsonResponse([
                'message' => 'Unable to issue token right now. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}


