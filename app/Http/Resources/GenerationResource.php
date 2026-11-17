<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Generation */
final class GenerationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $imageExpired = $this->image_url_expires_at?->isPast() ?? false;
        // Only ever hand the browser an https link (it ends up in an <a href> and <img src>).
        $imageUrl = str_starts_with((string) $this->image_url, 'https://') ? $this->image_url : null;

        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
            'size' => $this->size->value,
            'quality' => $this->quality->value,
            'reference_images' => $this->reference_images,
            'status' => $this->status->value,
            'estimated_price_nanos' => $this->estimated_price_nanos,
            'max_price_nanos' => $this->max_price_nanos,
            'price_nanos' => $this->price_nanos,
            'tokens' => $this->input_text_tokens === null ? null : [
                'input_text' => $this->input_text_tokens,
                'input_image' => $this->input_image_tokens,
                'output' => $this->output_tokens,
                'reasoning' => $this->reasoning_tokens,
            ],
            'image_url' => $imageExpired ? null : $imageUrl,
            'image_expired' => $imageExpired,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
