<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class RecaptchaVerifierService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private bool $enabled,
        private string $siteKey,
        private string $secretKey,
        private string $action,
        private float $minScore
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getSiteKey(): string
    {
        return $this->siteKey;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function verifyV3Token(?string $token, ?string $clientIp): RecaptchaVerificationResult
    {
        if (!$this->enabled) {
            return new RecaptchaVerificationResult(
                isValid: true,
                score: 1.0,
                action: $this->action
            );
        }

        if ($token === null || trim($token) === '') {
            return new RecaptchaVerificationResult(
                isValid: false,
                score: 0.0,
                action: '',
                errorCodes: ['missing-input-response']
            );
        }

        if (trim($this->secretKey) === '') {
            $this->logger->error('[RECAPTCHA - CONFIG ERROR]', [
                'error' => 'Missing secret key while reCAPTCHA is enabled.',
            ]);

            return new RecaptchaVerificationResult(
                isValid: false,
                score: 0.0,
                action: '',
                errorCodes: ['missing-input-secret']
            );
        }

        try {
            $response = $this->httpClient->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
                'body' => [
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $clientIp,
                ],
            ]);

            /** @var array{success?: bool, score?: float|int|string, action?: string, error-codes?: array<int, string>} $payload */
            $payload = $response->toArray(false);
            $success = (bool) ($payload['success'] ?? false);
            $score = (float) ($payload['score'] ?? 0.0);
            $action = (string) ($payload['action'] ?? '');
            /** @var string[] $errorCodes */
            $errorCodes = is_array($payload['error-codes'] ?? null) ? $payload['error-codes'] : [];

            $isValid = $success
                && $action === $this->action
                && $score >= $this->minScore;

            if (!$isValid) {
                $this->logger->warning('[RECAPTCHA - VERIFICATION FAILED]', [
                    'success' => $success,
                    'score' => $score,
                    'expected_action' => $this->action,
                    'action' => $action,
                    'error_codes' => $errorCodes,
                ]);
            }

            return new RecaptchaVerificationResult(
                isValid: $isValid,
                score: $score,
                action: $action,
                errorCodes: $errorCodes
            );
        } catch (ExceptionInterface $exception) {
            $this->logger->error('[RECAPTCHA - REQUEST FAILED]', [
                'error' => $exception->getMessage(),
            ]);

            return new RecaptchaVerificationResult(
                isValid: false,
                score: 0.0,
                action: '',
                errorCodes: ['request-failed']
            );
        }
    }
}

