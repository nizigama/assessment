<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BuyCreditsRequest extends FormRequest
{
    /** Whole-dollar packs only; this is a fake purchase. */
    public const PACKS = [5, 10, 25];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['dollars' => ['required', 'integer', Rule::in(self::PACKS)]];
    }
}
