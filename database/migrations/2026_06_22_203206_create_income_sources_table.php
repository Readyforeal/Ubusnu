<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('cadence', 16); // weekly | biweekly | semi_monthly | monthly
            $table->date('next_expected_on');
            $table->smallInteger('secondary_day_of_month')->nullable();
            $table->bigInteger('expected_amount_cents');
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('match_description', 255)->nullable();
            $table->string('color', 16)->nullable();
            $table->text('notes')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_sources');
    }
};
