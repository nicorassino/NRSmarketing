<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('objective')->nullable();
            $table->string('target_niche')->nullable();
            $table->string('target_location')->nullable();
            $table->string('status')->default('draft');
            // Status flow: draft → analyzing → mission_review → scouting → inbox_review → executing → completed
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('run_number')->default(1);
            $table->string('status')->default('running'); // running, paused, completed, failed
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'run_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_runs');
        Schema::dropIfExists('campaigns');
    }
};
