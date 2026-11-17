<?php

declare(strict_types=1);

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;

require_once __DIR__.'/../Pixelforge.php';

beforeEach(function () {
    config(['services.pixelforge.webhook_secret' => TEST_WEBHOOK_SECRET]);

    // A submitted generation: $1 balance, with its max price ($0.10) on hold.
    $this->user = User::factory()->create(['balance_nanos' => 900_000_000]);
    $this->generation = Generation::factory()->for($this->user)->create(['status' => GenerationStatus::Queued, 'max_price_nanos' => 100_000_000]);
    $this->user->creditTransactions()->create(['generation_id' => $this->generation->id, 'type' => CreditTransactionType::Hold, 'amount_nanos' => -100_000_000]);
});

it('charges the exact price once when a generation succeeds', function () {
    sendWebhook('evt_1', 'generation.succeeded', pixelforgeObject($this->generation, 'succeeded'))->assertNoContent();

    $generation = $this->generation->fresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->provider_cost_nanos)->toBe(169_080_000)
        ->and($generation->price_nanos)->toBe(211_350_000)          // +25%
        ->and($generation->image_url)->not->toBeNull()
        // $0.90 + $0.10 hold released - $0.21135 charged
        ->and($this->user->fresh()->balance_nanos)->toBe(788_650_000);
});

it('records an event delivered twice only once', function () {
    $object = pixelforgeObject($this->generation, 'succeeded');

    sendWebhook('evt_1', 'generation.succeeded', $object)->assertNoContent();
    sendWebhook('evt_1', 'generation.succeeded', $object)->assertNoContent();

    expect($this->user->creditTransactions()->where('type', 'charge')->count())->toBe(1)
        ->and($this->user->fresh()->balance_nanos)->toBe(788_650_000);
});

it('does not charge twice when Pixelforge sends a second success event with a new id', function () {
    $object = pixelforgeObject($this->generation, 'succeeded');

    sendWebhook('evt_1', 'generation.succeeded', $object);
    sendWebhook('evt_2', 'generation.succeeded', $object)->assertNoContent();

    expect($this->user->fresh()->balance_nanos)->toBe(788_650_000);
});

it('ignores a processing event that arrives after success', function () {
    sendWebhook('evt_2', 'generation.succeeded', pixelforgeObject($this->generation, 'succeeded'));
    sendWebhook('evt_1', 'generation.processing', pixelforgeObject($this->generation, 'processing'))->assertNoContent();

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Succeeded);
});

it('handles a webhook that arrives before our POST got its response', function () {
    $this->generation->forceFill(['status' => GenerationStatus::Pending])->save();

    sendWebhook('evt_1', 'generation.processing', pixelforgeObject($this->generation, 'processing'))->assertNoContent();

    $generation = $this->generation->fresh();
    expect($generation->status)->toBe(GenerationStatus::Processing)
        ->and($generation->pixelforge_id)->toBe('gen_'.strtolower($generation->id));
});

it('makes failed generations free and releases the hold', function () {
    sendWebhook('evt_1', 'generation.failed', pixelforgeObject($this->generation, 'failed'))->assertNoContent();

    $generation = $this->generation->fresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->price_nanos)->toBe(0)
        ->and($generation->error_message)->toBe('The model could not produce an image.')
        ->and($this->user->fresh()->balance_nanos)->toBe(1_000_000_000);
});

it('rejects a bad signature', function () {
    sendWebhook('evt_1', 'generation.succeeded', pixelforgeObject($this->generation, 'succeeded'), secret: 'whsec_wrong')
        ->assertUnauthorized();

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Queued);
});

it('rejects an old signature so captured requests cannot be replayed', function () {
    sendWebhook('evt_1', 'generation.succeeded', pixelforgeObject($this->generation, 'succeeded'), timestamp: time() - 301)
        ->assertUnauthorized();
});

it('rejects a request with no signature', function () {
    $this->postJson('/webhooks/pixelforge', ['id' => 'evt_1', 'type' => 'ping'])->assertUnauthorized();
});

it('accepts ping and unknown events', function () {
    sendWebhook('evt_ping', 'ping', [])->assertNoContent();
});
