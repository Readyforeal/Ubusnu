<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('coach_provider', 32)->default('gemini');
            $table->string('coach_model', 64)->nullable();
            $table->text('gemini_api_key')->nullable();
            $table->text('anthropic_api_key')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['coach_provider', 'coach_model', 'gemini_api_key', 'anthropic_api_key']);
        });
    }
};
