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
        Schema::create('user_manuals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('version')->default('1.0');
            $table->enum('type', ['installation', 'operation', 'maintenance', 'troubleshooting', 'complete']);
            $table->enum('format', ['pdf', 'html', 'markdown', 'docx']);
            $table->text('content')->nullable(); // Main content
            $table->json('sections')->nullable(); // Structured sections
            $table->string('file_path')->nullable(); // Generated file path
            $table->enum('status', ['draft', 'generating', 'completed', 'failed'])->default('draft');
            $table->text('generation_prompt')->nullable(); // AI prompt used
            $table->json('generation_config')->nullable(); // AI generation configuration
            $table->text('error_message')->nullable(); // Error details if generation failed
            $table->foreignId('generated_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->boolean('auto_update')->default(false); // Auto-regenerate on project changes
            $table->timestamps();
            
            $table->index(['project_id', 'type']);
            $table->index(['status', 'generated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_manuals');
    }
};