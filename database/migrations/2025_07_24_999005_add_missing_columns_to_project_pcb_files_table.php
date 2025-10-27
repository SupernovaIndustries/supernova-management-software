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
        Schema::table('project_pcb_files', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('project_pcb_files', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('file_hash');
            }
            
            if (!Schema::hasColumn('project_pcb_files', 'is_backup')) {
                $table->boolean('is_backup')->default(false)->after('is_primary');
            }
            
            if (!Schema::hasColumn('project_pcb_files', 'change_type')) {
                $table->enum('change_type', ['major', 'minor', 'patch'])->default('patch')->after('is_backup');
            }
            
            if (!Schema::hasColumn('project_pcb_files', 'uploaded_by')) {
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->after('change_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_pcb_files', function (Blueprint $table) {
            $table->dropColumn(['is_primary', 'is_backup', 'change_type', 'uploaded_by']);
        });
    }
};