<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A draft could not be produced. The message is written for the creator, so it is
 * safe to render directly in the product.
 */
class ContentGenerationException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
