<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->after('campaign_id')
                ->constrained()
                ->nullOnDelete();
        });

        // Best-effort backfill from campaign_id where possible.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                UPDATE chat_conversations cc
                INNER JOIN campaigns c ON c.id = cc.campaign_id
                SET cc.product_id = c.product_id
                WHERE cc.product_id IS NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
