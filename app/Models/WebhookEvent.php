<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $event_id
 * @property string $type
 * @property string|null $generation_id
 * @property array<string, mixed> $payload
 */
#[Fillable(['event_id', 'type', 'generation_id', 'payload'])]
class WebhookEvent extends Model
{
    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
