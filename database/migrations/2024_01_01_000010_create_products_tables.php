<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('value_proposition')->nullable();
            $table->text('pain_points_summary')->nullable();
            $table->string('status')->default('active'); // active, archived
            $table->boolean('is_analyzed')->default(false);
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('type')->default('manual'); // manual, brochure, specs, other
            $table->longText('extracted_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_documents');
        Schema::dropIfExists('products');
    }
};
