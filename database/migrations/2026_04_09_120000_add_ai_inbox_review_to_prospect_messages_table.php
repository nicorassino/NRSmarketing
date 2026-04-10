<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospect_messages', function (Blueprint $table) {
            $table->timestamp('ai_inbox_reviewed_at')->nullable()->after('delivery_metadata');
            $table->boolean('ai_inbox_suggest_send')->nullable()->after('ai_inbox_reviewed_at');
            $table->text('ai_inbox_review_notes')->nullable()->after('ai_inbox_suggest_send');
        });
    }

    public function down(): void
    {
        Schema::table('prospect_messages', function (Blueprint $table) {
            $table->dropColumn([
                'ai_inbox_reviewed_at',
                'ai_inbox_suggest_send',
                'ai_inbox_review_notes',
            ]);
        });
    }
};
