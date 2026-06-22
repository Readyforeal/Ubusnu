<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('kind', 16)->default('spending')->after('name');
            $table->foreignId('bucket_id')->nullable()->after('kind')->constrained()->nullOnDelete();
        });

        DB::table('categories')
            ->where('excluded_from_totals', true)
            ->update(['kind' => 'transfer']);

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('excluded_from_totals');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('excluded_from_totals')->default(false);
        });

        DB::table('categories')
            ->where('kind', 'transfer')
            ->update(['excluded_from_totals' => true]);

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['bucket_id']);
            $table->dropColumn(['kind', 'bucket_id']);
        });
    }
};
