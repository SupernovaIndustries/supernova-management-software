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
        Schema::create('assembly_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('assembly_checklist_templates')->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('board_serial_number')->nullable(); // Specific board being assembled
            $table->string('batch_number')->nullable(); // Production batch
            $table->integer('board_quantity')->default(1); // Number of boards in this assembly run
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'failed', 'on_hold'])->default('not_started');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('completion_percentage', 5, 2)->default(0); // 0-100%
            $table->integer('total_items')->default(0);
            $table->integer('completed_items')->default(0);
            $table->integer('failed_items')->default(0);
            $table->text('notes')->nullable();
            $table->json('board_specifications')->nullable(); // Override template specs for this instance
            $table->boolean('requires_supervisor_approval')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            
            $table->index(['project_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['template_id']);
            $table->index(['board_serial_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assembly_checklists');
    }
};
