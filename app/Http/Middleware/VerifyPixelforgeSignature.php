<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts a request only if Pixelforge signed it: header `Pixelforge-Signature: t=<unix>,v1=<hex>`
 * where v1 = HMAC-SHA256("<t>.<raw body>", webhook secret). Old timestamps are rejected so a
 * captured request can't be replayed later.
 */
final class VerifyPixelforgeSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.pixelforge.webhook_secret');
        $parts = [];

        foreach (explode(',', (string) $request->header('Pixelforge-Signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';

        $isFresh = ctype_digit($timestamp)
            && abs(time() - (int) $timestamp) <= config('services.pixelforge.webhook_tolerance_seconds');

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if ($secret === '' || ! $isFresh || ! hash_equals($expected, $signature)) {
            abort(401, 'Invalid Pixelforge signature.');
        }

        return $next($request);
    }
}
