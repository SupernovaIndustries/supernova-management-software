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
        Schema::create('assembly_checklist_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('assembly_checklists')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('assembly_checklist_items')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Who completed this step
            $table->json('response_data')->nullable(); // Actual response (checkbox, text, numbers, file paths, etc.)
            $table->enum('status', ['pending', 'completed', 'failed', 'skipped', 'needs_review'])->default('pending');
            $table->text('comments')->nullable(); // Additional comments from user
            $table->text('failure_reason')->nullable(); // Why this step failed
            $table->json('attachments')->nullable(); // Photos, files, signatures
            $table->decimal('measured_value', 10, 4)->nullable(); // For measurement type items
            $table->string('measurement_unit')->nullable(); // Unit of measurement
            $table->boolean('within_tolerance')->nullable(); // For measurement validation
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comments')->nullable();
            $table->timestamps();
            
            // Prevent duplicate responses for the same item in the same checklist
            $table->unique(['checklist_id', 'item_id'], 'unique_checklist_item_response');
            
            $table->index(['checklist_id', 'status']);
            $table->index(['user_id', 'completed_at']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assembly_checklist_responses');
    }
};
