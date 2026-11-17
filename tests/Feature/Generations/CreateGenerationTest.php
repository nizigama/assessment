<?php

declare(strict_types=1);

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/../Pixelforge.php';

beforeEach(function () {
    Http::fake(['*/pricing' => Http::response(json_decode(file_get_contents(__DIR__.'/../../fixtures/pricing.json'), true))]);
    Queue::fake();
});

it('holds the maximum price and queues the generation', function () {
    $user = User::factory()->create(['balance_nanos' => 1_000_000_000]);

    $this->actingAs($user)->post('/generations', [
        'prompt' => 'A lighthouse at dusk',
        'size' => '1024x1024',
        'quality' => 'low',
    ])->assertRedirect('/history');

    $generation = Generation::sole();
    expect($generation->status)->toBe(GenerationStatus::Pending)
        ->and($generation->max_price_nanos)->toBeGreaterThan($generation->estimated_price_nanos)
        ->and($user->fresh()->balance_nanos)->toBe(1_000_000_000 - $generation->max_price_nanos);

    Queue::assertPushed(SubmitGeneration::class);
});

it('blocks generating when the user cannot afford the maximum price', function () {
    $user = User::factory()->create(['balance_nanos' => 1_000]);

    $this->actingAs($user)->from('/generate')->post('/generations', [
        'prompt' => 'A lighthouse at dusk',
        'size' => '1024x1024',
        'quality' => 'high',
    ])->assertRedirect('/generate')->assertSessionHasErrors('credits');

    expect(Generation::count())->toBe(0)
        ->and($user->fresh()->balance_nanos)->toBe(1_000);
});

it('validates the request against Pixelforge limits', function () {
    $user = User::factory()->create(['balance_nanos' => 1_000_000_000]);

    $this->actingAs($user)->post('/generations', [
        'prompt' => str_repeat('a', 4001),
        'size' => '512x512',
        'quality' => 'ultra',
        'reference_images' => ['a', 'b', 'c', 'd', 'e'],
    ])->assertSessionHasErrors(['prompt', 'size', 'quality', 'reference_images']);

    $this->actingAs($user)->post('/generations', [
        'prompt' => 'ok',
        'size' => '1024x1024',
        'quality' => 'low',
        'reference_images' => ['file:///etc/passwd'],
    ])->assertSessionHasErrors(['reference_images.0']);
});

it('requires sign in', function () {
    $this->post('/generations', [])->assertRedirect('/login');
    $this->get('/history')->assertRedirect('/login');
});

it('only shows users their own generations', function () {
    $mine = Generation::factory()->create();
    Generation::factory()->create(['prompt' => 'someone else']);

    $this->actingAs($mine->user)->get('/history')
        ->assertInertia(fn ($page) => $page->component('generations/Index')->has('generations.data', 1));
});
