<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service'); // gemini_pro, gemini_flash, serpapi, whatsapp
            $table->string('operation'); // analyze, generate_message, search, send_message
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 8, 6)->default(0);
            $table->string('related_type')->nullable(); // App\Models\Campaign, etc.
            $table->unsignedBigInteger('related_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['service', 'created_at']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_logs');
    }
};
