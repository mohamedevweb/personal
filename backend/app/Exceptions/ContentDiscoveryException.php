<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Niche discovery could not run — a missing Apify token or a scraper failure. It
 * is caught by the ingestion job and logged; the feed keeps its existing posts.
 */
class ContentDiscoveryException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
