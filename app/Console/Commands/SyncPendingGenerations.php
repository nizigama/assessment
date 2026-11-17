<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\GenerationStatus;
use App\Exceptions\PixelforgeException;
use App\Models\Generation;
use App\Services\GenerationService;
use App\Services\PixelforgeClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safety net for webhooks that never arrive: asks Pixelforge directly about
 * generations that have been in progress for a while.
 */
#[Signature('generations:sync')]
#[Description('Poll Pixelforge for generations whose webhooks are overdue')]
final class SyncPendingGenerations extends Command
{
    public function handle(PixelforgeClient $pixelforge, GenerationService $generations): int
    {
        $overdue = Generation::query()
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->whereNotNull('pixelforge_id')
            ->where('updated_at', '<', now()->subMinutes(2))
            ->oldest('updated_at')
            ->limit(20)
            ->get();

        foreach ($overdue as $generation) {
            try {
                $generations->syncFromProvider($generation, $pixelforge->getGeneration($generation->pixelforge_id));
            } catch (PixelforgeException $e) {
                Log::warning('Could not poll Pixelforge generation', ['generation_id' => $generation->id, 'code' => $e->errorCode]);
            }
        }

        $this->info("Checked {$overdue->count()} generation(s).");

        return self::SUCCESS;
    }
}
