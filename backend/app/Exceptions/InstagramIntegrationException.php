<?php

namespace App\Exceptions;

use RuntimeException;

class InstagramIntegrationException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $metaCode = null)
    {
        parent::__construct($message);
    }
}
