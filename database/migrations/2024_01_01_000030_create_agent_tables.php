<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_run_id')->constrained()->cascadeOnDelete();
            $table->string('agent_type'); // analyst, scout, executor
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->longText('input_prompt')->nullable();
            $table->longText('output_result')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 8, 4)->default(0);
            $table->float('duration_seconds')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('context_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_run_id')->constrained()->cascadeOnDelete();
            $table->string('step'); // 01_product_analysis, 02_scout_mission, etc.
            $table->string('file_path');
            $table->string('format')->default('md'); // md, json
            $table->text('summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('context_files');
        Schema::dropIfExists('agent_executions');
    }
};
