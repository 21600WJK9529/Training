<?php

namespace App\Security;

use App\Service\ApiJwtTokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class ApiJwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly ApiJwtTokenService $tokenService)
    {
    }

    public function supports(Request $request): ?bool
    {
        $path = (string) $request->getPathInfo();

        if ($path === '/api/auth/token') {
            return false;
        }

        return str_starts_with($path, '/api/');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $authorizationHeader = (string) $request->headers->get('Authorization', '');

        if (!preg_match('/^Bearer\s+(.+)$/i', $authorizationHeader, $matches)) {
            throw new CustomUserMessageAuthenticationException('Missing or invalid Bearer token.');
        }

        $decodedToken = $this->tokenService->decodeTokenWithReason($matches[1]);
        $payload = $decodedToken['payload'];

        if ($payload === null) {
            throw new CustomUserMessageAuthenticationException(
                $this->buildInvalidTokenMessage($decodedToken['reason'])
            );
        }

        return new SelfValidatingPassport(
            new UserBadge(
                $payload['sub'],
                static fn (string $userIdentifier): InMemoryUser => new InMemoryUser($userIdentifier, '', $payload['roles'])
            )
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return new JsonResponse([
            'message' => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Authentication required.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function buildInvalidTokenMessage(?string $reason): string
    {
        return match ($reason) {
            'expired' => 'Token expired.',
            'signature_invalid' => 'Token signature is invalid.',
            'not_yet_valid' => 'Token cannot be used yet.',
            'invalid_claims' => 'Token claims are invalid.',
            'malformed' => 'Token format is invalid.',
            default => 'Token is invalid.',
        };
    }
}


