<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('bill_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->index('bill_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['bill_id']);
            $table->dropIndex(['bill_id']);
            $table->dropColumn('bill_id');
        });
    }
};
