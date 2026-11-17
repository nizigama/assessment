<?php

declare(strict_types=1);

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('adds a credit pack to the balance', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/credits', ['dollars' => 10])->assertRedirect();

    expect($user->fresh()->balance_nanos)->toBe(10_000_000_000)
        ->and($user->creditTransactions()->sole()->type->value)->toBe('purchase');
});

it('only sells the listed packs', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/credits', ['dollars' => 1_000_000])->assertSessionHasErrors('dollars');

    expect($user->fresh()->balance_nanos)->toBe(0);
});

it('hides the admin report from regular users', function () {
    $this->actingAs(User::factory()->create())->get('/admin/usage')->assertForbidden();
});

it('compares our provider costs with Pixelforge per day', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    Generation::factory()->create([
        'status' => GenerationStatus::Succeeded,
    ])->forceFill(['provider_cost_nanos' => 169_080_000, 'price_nanos' => 211_350_000, 'completed_at' => now()])->save();

    Http::fake(['*/usage*' => Http::response(['data' => [['date' => now()->toDateString(), 'cost_usd' => '0.169080000']]])]);

    $this->actingAs($admin)->get('/admin/usage')->assertInertia(fn ($page) => $page
        ->component('admin/UsageReport')
        ->where('days.0.provider_cost_nanos', 169_080_000)
        ->where('days.0.pixelforge_cost_nanos', 169_080_000)
        ->where('days.0.profit_nanos', 42_270_000));
});
