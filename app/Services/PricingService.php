<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Support\Nanodollars;

/**
 * Turns token counts into money.
 *
 *  - Provider cost: what Pixelforge charges us. Exact, matches GET /v1/usage.
 *  - Price: what the user pays = provider cost + 25%, rounded UP to a whole
 *    nanodollar (the markup can produce half a nanodollar; we never undercharge).
 */
final class PricingService
{
    private const TOKEN_TYPES = ['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'];

    /**
     * @param  array<string, int>  $tokens  Token counts keyed like Pixelforge's `usage`.
     * @param  array<string, string>  $rates  USD per 1M tokens, keyed like Pixelforge's `rates`.
     */
    public function providerCost(array $tokens, array $rates): int
    {
        // Sum tokens × (nanodollars per 1M tokens) first, divide once at the end.
        // Exact for any rate with up to 3 decimals; otherwise rounded up.
        $total = 0;
        foreach (self::TOKEN_TYPES as $type) {
            $total += ($tokens[$type] ?? 0) * Nanodollars::fromDecimalString($rates[$type]);
        }

        return intdiv($total + 999_999, 1_000_000);
    }

    public function userPrice(int $providerCost): int
    {
        return intdiv($providerCost * 5 + 3, 4);
    }

    /**
     * What a request will cost the user before it runs. Text and reasoning tokens
     * are only known afterwards, so we give a typical estimate and a worst case.
     *
     * @param  array<string, mixed>  $pricing  The GET /v1/pricing response.
     * @return array{estimated: int, max: int}
     */
    public function estimate(string $prompt, ImageSize $size, ImageQuality $quality, int $referenceImageCount, array $pricing): array
    {
        $tokens = $pricing['tokens'];
        $reasoning = $tokens['reasoning'][$quality->value];

        $textTokens = (int) ceil(mb_strlen($prompt) / $tokens['characters_per_text_token']);
        // Actual text tokens can be up to 15% above the estimate (whole-percent integer maths).
        $variancePercent = (int) round($tokens['text_token_variance'] * 100);
        $maxTextTokens = intdiv($textTokens * (100 + $variancePercent) + 99, 100);

        $known = [
            'input_image_tokens' => $referenceImageCount * $tokens['per_reference_image'],
            'output_tokens' => $tokens['output'][$quality->value][$size->value],
        ];

        $typical = $known + [
            'input_text_tokens' => $textTokens,
            'reasoning_tokens' => intdiv($reasoning['min'] + $reasoning['max'], 2),
        ];
        $worst = $known + [
            'input_text_tokens' => $maxTextTokens,
            'reasoning_tokens' => $reasoning['max'],
        ];

        return [
            'estimated' => $this->userPrice($this->providerCost($typical, $pricing['rates'])),
            'max' => $this->userPrice($this->providerCost($worst, $pricing['rates'])),
        ];
    }
}
