<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assembly_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('assembly_checklist_templates')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable(); // Detailed step-by-step instructions
            $table->enum('type', ['checkbox', 'text', 'number', 'measurement', 'photo', 'file', 'signature', 'multiselect'])->default('checkbox');
            $table->json('options')->nullable(); // For multiselect, dropdown options, validation rules
            $table->boolean('is_required')->default(true);
            $table->boolean('is_critical')->default(false); // Critical steps that block progression
            $table->integer('sort_order')->default(0);
            $table->string('category')->nullable(); // Group items by category (e.g., "Pre-assembly", "SMD Placement", "Testing")
            $table->json('validation_rules')->nullable(); // Min/max values, regex patterns, etc.
            $table->string('reference_image')->nullable(); // Path to reference image
            $table->text('safety_notes')->nullable(); // Safety warnings for this step
            $table->integer('estimated_minutes')->nullable(); // Time estimate for this step
            $table->timestamps();
            
            $table->index(['template_id', 'sort_order']);
            $table->index(['template_id', 'category']);
            $table->index(['is_critical']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assembly_checklist_items');
    }
};
