<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Pixelforge answered with an error (or could not be reached). */
final class PixelforgeException extends RuntimeException
{
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $errorCode,
        string $message,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Worth trying again later: rate limits, overloads, server errors and network
     * failures. Retrying is safe because every create call carries an Idempotency-Key.
     */
    public function isTemporary(): bool
    {
        return in_array($this->errorCode, ['rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded', 'connection_failed'], true)
            || $this->httpStatus >= 500;
    }
}
