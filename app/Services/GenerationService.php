<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Exceptions\InsufficientCreditsException;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Models\WebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class GenerationService
{
    public function __construct(
        private readonly PixelforgeClient $pixelforge,
        private readonly PricingService $pricing,
        private readonly CreditLedger $ledger,
    ) {}

    /**
     * Save the request, set aside its maximum possible price, and queue it for Pixelforge.
     *
     * @param  list<string>  $referenceImages
     */
    public function create(User $user, string $prompt, ImageSize $size, ImageQuality $quality, array $referenceImages): Generation
    {
        $estimate = $this->pricing->estimate($prompt, $size, $quality, count($referenceImages), $this->pixelforge->pricing());

        $generation = DB::transaction(function () use ($user, $prompt, $size, $quality, $referenceImages, $estimate): Generation {
            // Lock the user row so two quick submits can't both spend the same credits.
            $balance = User::whereKey($user->id)->lockForUpdate()->value('balance_nanos');

            if ($balance < $estimate['max']) {
                throw new InsufficientCreditsException($balance, $estimate['max']);
            }

            $generation = Generation::create([
                'user_id' => $user->id,
                'prompt' => $prompt,
                'size' => $size,
                'quality' => $quality,
                'reference_images' => $referenceImages,
                'status' => GenerationStatus::Pending,
                'estimated_price_nanos' => $estimate['estimated'],
                'max_price_nanos' => $estimate['max'],
            ]);

            $this->ledger->record($user, CreditTransactionType::Hold, -$estimate['max'], $generation);

            return $generation;
        });

        SubmitGeneration::dispatch($generation);

        return $generation;
    }

    /**
     * Handle a verified Pixelforge webhook. Recording the event and applying it happen
     * in one transaction: if applying fails, the event isn't recorded either, we return
     * an error, and Pixelforge's retry gets a clean second chance.
     *
     * @param  array<string, mixed>  $event
     */
    public function handleWebhook(array $event): void
    {
        $object = $event['data']['object'] ?? [];
        $generationId = $object['metadata']['generation_id'] ?? null;

        DB::transaction(function () use ($event, $object, $generationId): void {
            $isNew = WebhookEvent::insertOrIgnore([
                'event_id' => $event['id'],
                'type' => $event['type'],
                'generation_id' => is_string($generationId) ? $generationId : null,
                'payload' => json_encode($event),
                'created_at' => now(),
                'updated_at' => now(),
            ]) === 1;

            if (! $isNew) {
                Log::info('Duplicate Pixelforge webhook ignored', ['event_id' => $event['id']]);

                return;
            }

            if (! str_starts_with($event['type'], 'generation.') || ! is_string($generationId)) {
                return;
            }

            $generation = Generation::find($generationId);

            if ($generation === null) {
                Log::warning('Pixelforge webhook for unknown generation', ['event_id' => $event['id'], 'generation_id' => $generationId]);

                return;
            }

            $this->syncFromProvider($generation, $object);
        });
    }

    /**
     * The one place a generation's status and money change. Called with Pixelforge's
     * generation object from the POST response, a webhook, or the fallback poller.
     *
     * Safe to call any number of times, in any order: status only moves forward, and
     * a finished generation is never touched again.
     *
     * @param  array<string, mixed>  $object  Pixelforge's generation object.
     */
    public function syncFromProvider(Generation $generation, array $object): void
    {
        DB::transaction(function () use ($generation, $object): void {
            $generation = Generation::whereKey($generation->id)->lockForUpdate()->firstOrFail();
            $incoming = GenerationStatus::from($object['status']);

            $generation->pixelforge_id ??= $object['id'];

            if ($generation->status->isFinal()) {
                $this->recordCostOfAbandonedGeneration($generation, $incoming, $object);

                return;
            }

            if ($incoming->step() <= $generation->status->step()) {
                $generation->save(); // may have just learned pixelforge_id

                return;
            }

            match ($incoming) {
                GenerationStatus::Succeeded => $this->markSucceeded($generation, $object),
                GenerationStatus::Failed => $this->markFailed(
                    $generation,
                    $object['error']['code'] ?? 'generation_failed',
                    $object['error']['message'] ?? 'The generation failed.',
                ),
                default => $generation->forceFill(['status' => $incoming])->save(),
            };
        });
    }

    /**
     * Fail a generation and give back its hold. Used when Pixelforge reports a failure,
     * and when Pixelforge never accepted it (blocked prompt, limits, outage).
     */
    public function markFailed(Generation $generation, string $code, string $message): void
    {
        DB::transaction(function () use ($generation, $code, $message): void {
            $generation = Generation::whereKey($generation->id)->lockForUpdate()->firstOrFail();

            if ($generation->status->isFinal()) {
                return;
            }

            $generation->forceFill([
                'status' => GenerationStatus::Failed,
                'error_code' => $code,
                'error_message' => mb_substr($message, 0, 500),
                'provider_cost_nanos' => 0,
                'price_nanos' => 0,
                'completed_at' => now(),
            ])->save();

            $this->ledger->record($generation->user, CreditTransactionType::Release, $generation->max_price_nanos, $generation);
        });
    }

    /** @param  array<string, mixed>  $object */
    private function markSucceeded(Generation $generation, array $object): void
    {
        $usage = $object['usage'];
        // Always price with the rates Pixelforge froze on this generation, not today's.
        $providerCost = $this->pricing->providerCost($usage, $object['rates']);
        $price = $this->pricing->userPrice($providerCost);

        $generation->forceFill([
            'status' => GenerationStatus::Succeeded,
            'rates' => $object['rates'],
            'input_text_tokens' => $usage['input_text_tokens'],
            'input_image_tokens' => $usage['input_image_tokens'],
            'output_tokens' => $usage['output_tokens'],
            'reasoning_tokens' => $usage['reasoning_tokens'],
            'provider_cost_nanos' => $providerCost,
            'price_nanos' => $price,
            'image_url' => $object['image_url'],
            'image_url_expires_at' => $object['image_url_expires_at'] ? CarbonImmutable::parse($object['image_url_expires_at']) : null,
            'completed_at' => CarbonImmutable::parse($object['completed_at'] ?? 'now'),
        ])->save();

        // Give back the whole hold, then take the exact price.
        $this->ledger->record($generation->user, CreditTransactionType::Release, $generation->max_price_nanos, $generation);
        $this->ledger->record($generation->user, CreditTransactionType::Charge, -$price, $generation);
    }

    /**
     * Rare edge case: we gave up on submitting (e.g. the connection dropped on every
     * retry) and told the user it failed, but Pixelforge had in fact accepted it and
     * later finished it. The user isn't charged, but Pixelforge did bill us, so we
     * record that provider cost to keep our books matching GET /v1/usage.
     *
     * @param  array<string, mixed>  $object
     */
    private function recordCostOfAbandonedGeneration(Generation $generation, GenerationStatus $incoming, array $object): void
    {
        $alreadyRecorded = $generation->status === GenerationStatus::Succeeded || $generation->provider_cost_nanos > 0;

        if ($incoming !== GenerationStatus::Succeeded || $alreadyRecorded) {
            $generation->save();

            return;
        }

        $generation->forceFill([
            'rates' => $object['rates'],
            'provider_cost_nanos' => $this->pricing->providerCost($object['usage'], $object['rates']),
            'completed_at' => CarbonImmutable::parse($object['completed_at'] ?? 'now'),
        ])->save();

        Log::warning('Pixelforge billed a generation we had marked failed', ['generation_id' => $generation->id]);
    }
}
