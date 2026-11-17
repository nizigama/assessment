<?php

declare(strict_types=1);

namespace App\Enums;

enum GenerationStatus: string
{
    /** Saved by us, not yet accepted by Pixelforge. */
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    /**
     * How far along the lifecycle this status is. A generation only ever moves to a
     * higher step, which is what makes late or out-of-order webhooks harmless.
     */
    public function step(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Queued => 1,
            self::Processing => 2,
            self::Succeeded, self::Failed => 3,
        };
    }
}
