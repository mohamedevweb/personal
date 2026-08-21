<?php

namespace App\Services\Discovery;

class ContentSafetyDecision
{
    public const ALLOWED = 'allowed';

    public const BLOCKED = 'blocked';

    public const PENDING = 'pending';

    /** @param list<string> $reasons */
    public function __construct(
        public readonly string $status,
        public readonly array $reasons = [],
    ) {}

    public function isAllowed(): bool
    {
        return $this->status === self::ALLOWED;
    }
}
