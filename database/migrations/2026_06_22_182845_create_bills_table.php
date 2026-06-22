<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('cadence', 16);
            $table->smallInteger('due_day_of_month');
            $table->smallInteger('due_month_of_year')->nullable();
            $table->bigInteger('expected_amount_cents');
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('match_description', 255)->nullable();
            $table->text('manually_marked_paid_periods')->nullable();
            $table->string('color', 16)->nullable();
            $table->text('notes')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
