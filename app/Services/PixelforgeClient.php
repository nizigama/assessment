<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PixelforgeException;
use App\Models\Generation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Thin wrapper around the Pixelforge HTTP API. Returns decoded JSON or throws PixelforgeException. */
final class PixelforgeClient
{
    /**
     * Current rates and token tables. Public, and cached briefly because every
     * page load of the Generate form needs it.
     *
     * @return array<string, mixed>
     */
    public function pricing(): array
    {
        return Cache::remember('pixelforge.pricing', now()->addMinutes(10), fn (): array => $this->send(
            fn (PendingRequest $http): Response => $http->get('pricing'),
        ));
    }

    /** @return array<string, mixed> The generation object. */
    public function createGeneration(Generation $generation): array
    {
        $body = [
            'prompt' => $generation->prompt,
            'size' => $generation->size->value,
            'quality' => $generation->quality->value,
            'metadata' => ['generation_id' => $generation->id],
        ];

        if ($generation->reference_images !== []) {
            $body['reference_images'] = $generation->reference_images;
        }

        return $this->send(fn (PendingRequest $http): Response => $http
            ->withHeader('Idempotency-Key', $generation->id)
            ->post('generations', $body));
    }

    /** @return array<string, mixed> The generation object. */
    public function getGeneration(string $pixelforgeId): array
    {
        return $this->send(fn (PendingRequest $http): Response => $http->get('generations/'.rawurlencode($pixelforgeId)));
    }

    /** @return array<string, mixed> The usage report for this API key. */
    public function usage(string $startDate, string $endDate): array
    {
        return $this->send(fn (PendingRequest $http): Response => $http->get('usage', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]));
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     * @return array<string, mixed>
     */
    private function send(callable $request): array
    {
        $http = Http::baseUrl(config('services.pixelforge.base_url'))
            ->withToken((string) config('services.pixelforge.api_key'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20);

        try {
            $response = $request($http);
        } catch (ConnectionException $e) {
            throw new PixelforgeException(0, 'connection_failed', 'Could not reach Pixelforge: '.$e->getMessage());
        }

        if ($response->successful()) {
            return $response->json();
        }

        $retryAfter = $response->header('Retry-After');

        throw new PixelforgeException(
            httpStatus: $response->status(),
            errorCode: (string) $response->json('error.code', 'http_'.$response->status()),
            message: (string) $response->json('error.message', 'Pixelforge returned HTTP '.$response->status()),
            retryAfterSeconds: is_numeric($retryAfter) ? (int) $retryAfter : null,
        );
    }
}
