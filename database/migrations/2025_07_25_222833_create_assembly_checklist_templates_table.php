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
        Schema::create('assembly_checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('board_type', ['smd', 'through_hole', 'mixed', 'prototype', 'production', 'generic'])->default('generic');
            $table->enum('complexity_level', ['simple', 'medium', 'complex', 'expert'])->default('medium');
            $table->json('pcb_specifications')->nullable(); // Store PCB size, layer count, etc.
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // Default template for board type
            $table->integer('estimated_time_minutes')->nullable(); // Estimated completion time
            $table->text('requirements')->nullable(); // Special tools, environment requirements
            $table->json('metadata')->nullable(); // Additional configuration
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['board_type', 'is_active']);
            $table->index(['complexity_level']);
            $table->index(['is_default', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assembly_checklist_templates');
    }
};
