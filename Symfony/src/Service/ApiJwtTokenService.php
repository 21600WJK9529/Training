<?php

namespace App\Service;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use UnexpectedValueException;

final readonly class ApiJwtTokenService
{
    public function __construct(
        private string $secret,
        private int $ttl
    ) {
    }

    /**
     * @param string[] $roles
     */
    public function createToken(string $subject, array $roles): string
    {
        $now = time();

        $payload = [
            'sub' => $subject,
            'roles' => $roles,
            'iat' => $now,
            'exp' => $now + $this->ttl,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    /**
     * @return array{payload: array{sub: string, roles: string[], iat: int, exp: int}|null, reason: string|null}
     */
    public function decodeTokenWithReason(string $token): array
    {
        try {
            $decoded = (array) JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (ExpiredException) {
            return ['payload' => null, 'reason' => 'expired'];
        } catch (SignatureInvalidException) {
            return ['payload' => null, 'reason' => 'signature_invalid'];
        } catch (BeforeValidException) {
            return ['payload' => null, 'reason' => 'not_yet_valid'];
        } catch (UnexpectedValueException) {
            return ['payload' => null, 'reason' => 'malformed'];
        } catch (\Throwable) {
            return ['payload' => null, 'reason' => 'unknown'];
        }

        $subject = isset($decoded['sub']) && is_string($decoded['sub']) ? $decoded['sub'] : null;
        $roles = isset($decoded['roles']) && is_array($decoded['roles']) ? $decoded['roles'] : null;
        $issuedAt = isset($decoded['iat']) && is_int($decoded['iat']) ? $decoded['iat'] : null;
        $expiresAt = isset($decoded['exp']) && is_int($decoded['exp']) ? $decoded['exp'] : null;

        if ($subject === null || $roles === null || $issuedAt === null || $expiresAt === null) {
            return ['payload' => null, 'reason' => 'invalid_claims'];
        }

        $roleValues = array_values(array_filter($roles, static fn (mixed $role): bool => is_string($role) && $role !== ''));

        return [
            'payload' => [
                'sub' => $subject,
                'roles' => $roleValues,
                'iat' => $issuedAt,
                'exp' => $expiresAt,
            ],
            'reason' => null,
        ];
    }

}

