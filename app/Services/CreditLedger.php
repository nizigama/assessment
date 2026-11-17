<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;

/**
 * Every balance change is a ledger row plus a matching update of users.balance_nanos.
 * Call it inside a DB transaction so both are saved together or not at all.
 */
final class CreditLedger
{
    public function record(User $user, CreditTransactionType $type, int $amountNanos, ?Generation $generation = null): void
    {
        // A second hold/release/charge for the same generation violates the
        // unique index and throws, rolling the whole transaction back.
        CreditTransaction::create([
            'user_id' => $user->id,
            'generation_id' => $generation?->id,
            'type' => $type,
            'amount_nanos' => $amountNanos,
        ]);

        User::whereKey($user->id)->increment('balance_nanos', $amountNanos);
    }

    public function purchase(User $user, int $amountNanos): void
    {
        $this->record($user, CreditTransactionType::Purchase, $amountNanos);
    }
}
