<?php

declare(strict_types=1);

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/../Pixelforge.php';

beforeEach(function () {
    $this->user = User::factory()->create(['balance_nanos' => 900_000_000]);
    $this->generation = Generation::factory()->for($this->user)->create(['max_price_nanos' => 100_000_000]);
    $this->user->creditTransactions()->create(['generation_id' => $this->generation->id, 'type' => CreditTransactionType::Hold, 'amount_nanos' => -100_000_000]);
});

function runJob(Generation $generation, int $attempt = 1): Job
{
    $queueJob = Mockery::mock(Job::class)->shouldIgnoreMissing();
    $queueJob->shouldReceive('attempts')->andReturn($attempt);

    $job = (new SubmitGeneration($generation))->setJob($queueJob);
    app()->call([$job, 'handle']);

    return $queueJob;
}

it('sends the idempotency key and stores the Pixelforge id', function () {
    Http::fake(['*/generations' => Http::response(pixelforgeObject($this->generation, 'queued'), 202)]);

    runJob($this->generation);

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Queued)
        ->and($this->generation->fresh()->pixelforge_id)->not->toBeNull();
    Http::assertSent(fn ($request) => $request->header('Idempotency-Key')[0] === $this->generation->id
        && $request['metadata']['generation_id'] === $this->generation->id);
});

it('retries later when Pixelforge is overloaded', function (int $status, string $code) {
    Http::fake(['*/generations' => Http::response(['error' => ['code' => $code, 'message' => 'busy']], $status, ['Retry-After' => '7'])]);

    $queueJob = runJob($this->generation);

    $queueJob->shouldHaveReceived('release')->with(7);
    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Pending);
})->with([
    [503, 'overloaded'],
    [429, 'rate_limit_exceeded'],
    [429, 'concurrency_limit_exceeded'],
]);

it('gives up after the last attempt, free of charge', function () {
    Http::fake(['*/generations' => Http::response(['error' => ['code' => 'overloaded', 'message' => 'busy']], 503)]);

    runJob($this->generation, attempt: 8);

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Failed)
        ->and($this->user->fresh()->balance_nanos)->toBe(1_000_000_000);
});

it('fails a blocked prompt right away, free of charge', function () {
    Http::fake(['*/generations' => Http::response(['error' => ['code' => 'content_policy_violation', 'message' => 'no']], 400)]);

    runJob($this->generation);

    $generation = $this->generation->fresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error_message)->toContain('content policy')
        ->and($this->user->fresh()->balance_nanos)->toBe(1_000_000_000);
});

it('does nothing if a webhook already moved the generation on', function () {
    Http::fake();
    $this->generation->forceFill(['status' => GenerationStatus::Processing])->save();

    runJob($this->generation);

    Http::assertNothingSent();
});
