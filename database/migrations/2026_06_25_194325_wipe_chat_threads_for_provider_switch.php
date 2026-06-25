<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_messages')) {
            DB::table('chat_messages')->delete();
        }
        if (Schema::hasTable('chat_threads')) {
            DB::table('chat_threads')->delete();
        }
    }

    public function down(): void
    {
        // No-op: wiped data cannot be restored.
    }
};
