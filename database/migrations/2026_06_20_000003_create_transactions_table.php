<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('occurred_on');
            $table->text('description');
            $table->bigInteger('amount_cents');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dedup_hash');
            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->string('source')->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['account_id', 'dedup_hash']);
            $table->index(['account_id', 'occurred_on']);
            $table->index('import_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
