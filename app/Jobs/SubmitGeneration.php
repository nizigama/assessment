<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Exceptions\PixelforgeException;
use App\Models\Generation;
use App\Services\GenerationService;
use App\Services\PixelforgeClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a generation to Pixelforge. Busy signals (rate limit, concurrency limit,
 * overloaded) are retried with growing waits. Every attempt sends the same
 * Idempotency-Key, so a retry can never create or bill a second generation.
 */
final class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    /** Roughly 5 minutes of waiting in total before we give up. */
    public int $tries = 8;

    public function __construct(public readonly Generation $generation) {}

    public function handle(PixelforgeClient $pixelforge, GenerationService $generations): void
    {
        if ($this->generation->fresh()?->status !== GenerationStatus::Pending) {
            return; // Already accepted (a webhook may even have beaten us here) or already failed.
        }

        try {
            $object = $pixelforge->createGeneration($this->generation);
        } catch (PixelforgeException $e) {
            if ($e->isTemporary() && $this->attempts() < $this->tries) {
                $wait = max($e->retryAfterSeconds ?? 0, min(60, 5 * 2 ** ($this->attempts() - 1)));
                Log::info('Pixelforge busy, retrying generation', ['generation_id' => $this->generation->id, 'code' => $e->errorCode, 'wait_seconds' => $wait]);
                $this->release($wait);

                return;
            }

            $generations->markFailed($this->generation, $e->errorCode, $this->friendlyMessage($e));

            return;
        }

        $generations->syncFromProvider($this->generation, $object);
    }

    /** Anything unexpected (bug, DB outage...): never leave credits held forever. */
    public function failed(?Throwable $exception): void
    {
        app(GenerationService::class)->markFailed(
            $this->generation,
            'submit_failed',
            'We could not send this to Pixelforge. You were not charged; please try again.',
        );
    }

    private function friendlyMessage(PixelforgeException $e): string
    {
        return match ($e->errorCode) {
            'content_policy_violation' => 'Pixelforge rejected this prompt under its content policy. You were not charged.',
            'daily_limit_exceeded' => 'Pixelforge\'s daily generation limit has been reached. You were not charged; please try again tomorrow (UTC).',
            'rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded', 'connection_failed' => 'Pixelforge stayed busy for several minutes. You were not charged; please try again.',
            default => 'Pixelforge could not accept this request ('.$e->errorCode.'). You were not charged.',
        };
    }
}
