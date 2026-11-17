<?php

declare(strict_types=1);

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Services\PricingService;
use App\Support\Nanodollars;

const RATES = [
    'input_text_tokens' => '5.00',
    'input_image_tokens' => '10.00',
    'output_tokens' => '40.00',
    'reasoning_tokens' => '12.50',
];

function pricingTables(): array
{
    return [
        'rates' => RATES,
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => [
                'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
            ],
            'reasoning' => [
                'low' => ['min' => 0, 'max' => 500],
                'medium' => ['min' => 200, 'max' => 2000],
                'high' => ['min' => 1000, 'max' => 8000],
            ],
        ],
    ];
}

it('converts dollar strings to nanodollars and back without floats', function () {
    expect(Nanodollars::fromDecimalString('12.50'))->toBe(12_500_000_000)
        ->and(Nanodollars::fromDecimalString('0.169080000'))->toBe(169_080_000)
        ->and(Nanodollars::fromDecimalString('5'))->toBe(5_000_000_000)
        ->and(Nanodollars::toDecimalString(169_080_000))->toBe('0.169080000')
        ->and(Nanodollars::toDecimalString(-1))->toBe('-0.000000001');
});

it('rejects malformed dollar strings', function () {
    Nanodollars::fromDecimalString('1e3');
})->throws(InvalidArgumentException::class);

it('matches the provider cost in the Pixelforge usage example exactly', function () {
    // Docs: 41 text, 0 image, 3300 output, 2950 reasoning tokens -> "0.169080000".
    $cost = (new PricingService)->providerCost([
        'input_text_tokens' => 41,
        'input_image_tokens' => 0,
        'output_tokens' => 3300,
        'reasoning_tokens' => 2950,
    ], RATES);

    expect(Nanodollars::toDecimalString($cost))->toBe('0.169080000');
});

it('adds a 25% markup, rounding up to the next nanodollar', function () {
    $pricing = new PricingService;

    expect($pricing->userPrice(100))->toBe(125)
        ->and($pricing->userPrice(2))->toBe(3)   // 2.5 -> 3
        ->and($pricing->userPrice(0))->toBe(0);
});

it('reproduces the docs worst-case example as the maximum price', function () {
    // Docs: high, 1536x1024, 400 characters, 2 reference images -> max provider cost $0.373875.
    $estimate = (new PricingService)->estimate(str_repeat('a', 400), ImageSize::Landscape, ImageQuality::High, 2, pricingTables());

    expect($estimate['max'])->toBe((new PricingService)->userPrice(373_875_000));
});

it('estimates with the typical reasoning tokens', function () {
    // low, 1024x1024, 8 chars -> 2 text tokens, 280 output, 250 reasoning.
    $estimate = (new PricingService)->estimate('a cat!!!', ImageSize::Square, ImageQuality::Low, 0, pricingTables());

    $cost = 2 * 5_000 + 280 * 40_000 + 250 * 12_500; // nanodollars per token
    expect($estimate['estimated'])->toBe((new PricingService)->userPrice($cost));
});
