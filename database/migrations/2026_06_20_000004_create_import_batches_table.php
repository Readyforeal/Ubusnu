<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->integer('row_count')->default(0);
            $table->integer('imported_count')->default(0);
            $table->integer('skipped_duplicate_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('import_batch_id')->references('id')->on('import_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['import_batch_id']);
        });

        Schema::dropIfExists('import_batches');
    }
};
