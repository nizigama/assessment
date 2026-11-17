<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('generation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            // Signed: purchases and releases are positive, holds and charges negative.
            $table->bigInteger('amount_nanos');
            $table->timestamps();

            // The database itself refuses a second hold, release or charge for the
            // same generation. Purchases have no generation, and NULLs never collide.
            $table->unique(['generation_id', 'type']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
