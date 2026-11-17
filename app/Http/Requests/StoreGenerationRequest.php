<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreGenerationRequest extends FormRequest
{
    /**
     * Mirrors Pixelforge's limits, so we reject bad input before holding any credits.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', Rule::enum(ImageSize::class)],
            'quality' => ['required', Rule::enum(ImageQuality::class)],
            'reference_images' => ['array', 'max:4'],
            // Only validated and passed on; we never fetch these URLs ourselves.
            'reference_images.*' => ['required', 'url:http,https', 'max:2048'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reference_images.max' => 'You can add up to 4 reference images.',
            'reference_images.*.url' => 'Each reference image must be an http(s) URL.',
        ];
    }

    /** @return list<string> */
    public function referenceImages(): array
    {
        return array_values($this->validated('reference_images', []));
    }
}
