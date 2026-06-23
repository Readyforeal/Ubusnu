<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('income_source_id')->nullable()->after('bill_id')->constrained()->nullOnDelete();
            $table->index('income_source_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['income_source_id']);
            $table->dropConstrainedForeignId('income_source_id');
        });
    }
};
