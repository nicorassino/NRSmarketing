<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_run_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website_url')->nullable();
            $table->string('instagram_handle')->nullable();
            $table->string('source')->default('serpapi'); // serpapi, manual, google_maps
            $table->json('raw_data')->nullable();
            $table->text('ai_analysis')->nullable();
            $table->string('status')->default('new'); // new, approved, rejected, contacted, converted
            $table->string('selected_channel')->nullable(); // whatsapp, email, instagram
            $table->unsignedInteger('score')->default(0); // 0-100
            $table->timestamps();
        });

        Schema::create('prospect_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained()->cascadeOnDelete();
            $table->string('channel'); // whatsapp, email, instagram
            $table->string('subject')->nullable(); // For email
            $table->text('content');
            $table->text('original_ai_content')->nullable();
            $table->string('status')->default('draft'); // draft, approved, sent, delivered, read, failed
            $table->timestamp('sent_at')->nullable();
            $table->json('delivery_metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_messages');
        Schema::dropIfExists('prospects');
    }
};
