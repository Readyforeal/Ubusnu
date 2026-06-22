<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('income_sources', function (Blueprint $table) {
            $table->smallInteger('primary_day_of_month')->nullable()->after('secondary_day_of_month');
        });
    }

    public function down(): void
    {
        Schema::table('income_sources', function (Blueprint $table) {
            $table->dropColumn('primary_day_of_month');
        });
    }
};
