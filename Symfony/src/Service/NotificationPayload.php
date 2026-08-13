<?php

namespace App\Service;

final readonly class NotificationPayload
{
    public function __construct(
        public string $target,
        public string $toEmail,
        public ?string $toName,
        public string $subject,
        public string $body
    ) {
    }
}

