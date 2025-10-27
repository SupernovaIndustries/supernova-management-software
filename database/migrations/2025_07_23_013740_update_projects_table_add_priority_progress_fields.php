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
        Schema::table('projects', function (Blueprint $table) {
            // Remove old text fields
            if (Schema::hasColumn('projects', 'priority')) {
                $table->dropColumn('priority');
            }
            if (Schema::hasColumn('projects', 'progress')) {
                $table->dropColumn('progress');
            }
            if (Schema::hasColumn('projects', 'milestones')) {
                $table->dropColumn('milestones');
            }
            
            // Add new foreign keys
            $table->foreignId('priority_id')->nullable()->constrained('project_priorities')->onDelete('set null');
            $table->foreignId('progress_id')->nullable()->constrained('project_progress')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['priority_id']);
            $table->dropForeign(['progress_id']);
            $table->dropColumn(['priority_id', 'progress_id']);
            
            // Restore old columns
            $table->string('priority')->nullable();
            $table->integer('progress')->default(0);
            $table->json('milestones')->nullable();
        });
    }
};
