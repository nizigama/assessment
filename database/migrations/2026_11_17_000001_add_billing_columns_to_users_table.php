<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->after('password');
            // Spendable credits in nanodollars (1 = $0.000000001). Kept in sync with
            // credit_transactions by CreditLedger, inside the same DB transaction.
            $table->bigInteger('balance_nanos')->default(0)->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_admin', 'balance_nanos']);
        });
    }
};
