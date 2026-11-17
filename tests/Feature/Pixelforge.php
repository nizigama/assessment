<?php

declare(strict_types=1);

/*
 * Helpers for faking Pixelforge in feature tests.
 */

use App\Models\Generation;
use Illuminate\Testing\TestResponse;

const TEST_WEBHOOK_SECRET = 'whsec_test';

function pixelforgeObject(Generation $generation, string $status, array $overrides = []): array
{
    $object = [
        'id' => 'gen_'.strtolower($generation->id),
        'object' => 'generation',
        'status' => $status,
        'prompt' => $generation->prompt,
        'size' => $generation->size->value,
        'quality' => $generation->quality->value,
        'reference_images' => [],
        'metadata' => ['generation_id' => $generation->id],
        'image_url' => null,
        'image_url_expires_at' => null,
        'usage' => null,
        'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
        'error' => null,
        'created_at' => now()->toIso8601ZuluString(),
        'started_at' => null,
        'completed_at' => null,
    ];

    if ($status === 'succeeded') {
        $object['usage'] = ['input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950, 'total_tokens' => 6291];
        $object['image_url'] = 'https://pixelforge.promptedit.dev/images/x.png';
        $object['image_url_expires_at'] = now()->addDay()->toIso8601ZuluString();
        $object['completed_at'] = now()->toIso8601ZuluString();
    }

    if ($status === 'failed') {
        $object['error'] = ['code' => 'generation_failed', 'message' => 'The model could not produce an image.'];
        $object['completed_at'] = now()->toIso8601ZuluString();
    }

    return array_replace($object, $overrides);
}

function sendWebhook(string $eventId, string $type, array $object, ?int $timestamp = null, ?string $secret = null): TestResponse
{
    $body = json_encode(['id' => $eventId, 'object' => 'event', 'type' => $type, 'created_at' => now()->toIso8601ZuluString(), 'data' => ['object' => $object]]);
    $timestamp ??= time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret ?? TEST_WEBHOOK_SECRET);

    return test()->call('POST', '/webhooks/pixelforge', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], $body);
}
