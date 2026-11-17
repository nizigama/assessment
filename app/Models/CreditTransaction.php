<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditTransactionType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $generation_id
 * @property CreditTransactionType $type
 * @property int $amount_nanos
 * @property CarbonImmutable $created_at
 */
#[Fillable(['user_id', 'generation_id', 'type', 'amount_nanos'])]
class CreditTransaction extends Model
{
    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    protected function casts(): array
    {
        return [
            'type' => CreditTransactionType::class,
            'amount_nanos' => 'integer',
        ];
    }
}
