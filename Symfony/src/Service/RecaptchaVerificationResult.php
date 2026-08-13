<?php

namespace App\Service;

final readonly class RecaptchaVerificationResult
{
    /**
     * @param string[] $errorCodes
     */
    public function __construct(
        public bool $isValid,
        public float $score,
        public string $action,
        public array $errorCodes = []
    ) {
    }
}

