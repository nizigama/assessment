import { parseUsd } from '@/lib/money';

/**
 * Client-side copy of App\Services\PricingService::estimate, so the cost updates
 * as you type. The server recomputes it on submit, and that is the figure held.
 * Keep the two in step.
 */
type TokenType =
    | 'input_text_tokens'
    | 'input_image_tokens'
    | 'output_tokens'
    | 'reasoning_tokens';

export type Pricing = {
    rates: Record<TokenType, string>;
    tokens: {
        characters_per_text_token: number;
        text_token_variance: number;
        per_reference_image: number;
        output: Record<string, Record<string, number>>;
        reasoning: Record<string, { min: number; max: number }>;
    };
};

function providerCost(
    tokens: Record<TokenType, number>,
    rates: Pricing['rates'],
): number {
    let total = 0;

    for (const type of Object.keys(tokens) as TokenType[]) {
        total += tokens[type] * parseUsd(rates[type]);
    }

    return Math.ceil(total / 1_000_000);
}

function userPrice(cost: number): number {
    return Math.floor((cost * 5 + 3) / 4);
}

export function estimatePrice(
    prompt: string,
    size: string,
    quality: string,
    referenceImageCount: number,
    pricing: Pricing,
) {
    const t = pricing.tokens;
    const reasoning = t.reasoning[quality];
    const characters = Array.from(prompt).length; // code points, like PHP's mb_strlen
    const textTokens = Math.ceil(characters / t.characters_per_text_token);
    const variancePercent = Math.round(t.text_token_variance * 100);
    const maxTextTokens = Math.floor(
        (textTokens * (100 + variancePercent) + 99) / 100,
    );

    const known = {
        input_image_tokens: referenceImageCount * t.per_reference_image,
        output_tokens: t.output[quality][size],
    };

    return {
        estimated: userPrice(
            providerCost(
                {
                    ...known,
                    input_text_tokens: textTokens,
                    reasoning_tokens: Math.floor(
                        (reasoning.min + reasoning.max) / 2,
                    ),
                },
                pricing.rates,
            ),
        ),
        max: userPrice(
            providerCost(
                {
                    ...known,
                    input_text_tokens: maxTextTokens,
                    reasoning_tokens: reasoning.max,
                },
                pricing.rates,
            ),
        ),
    };
}
