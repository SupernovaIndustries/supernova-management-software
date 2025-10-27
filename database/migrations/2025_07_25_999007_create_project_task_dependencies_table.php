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
        Schema::create('project_task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('predecessor_task_id')->constrained('project_tasks')->onDelete('cascade');
            $table->foreignId('successor_task_id')->constrained('project_tasks')->onDelete('cascade');
            $table->enum('dependency_type', ['finish_to_start', 'start_to_start', 'finish_to_finish', 'start_to_finish'])->default('finish_to_start');
            $table->integer('lag_days')->default(0); // Lag/lead time in days (negative for lead time)
            $table->timestamps();
            
            // Prevent duplicate dependencies
            $table->unique(['predecessor_task_id', 'successor_task_id'], 'unique_task_dependency');
            
            $table->index(['predecessor_task_id']);
            $table->index(['successor_task_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_task_dependencies');
    }
};
