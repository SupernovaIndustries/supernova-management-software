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
            // Add file-related columns if they don't exist
            if (!Schema::hasColumn('project_pcb_files', 'file_path')) {
                $table->string('file_path')->nullable()->after('file_type');
            }
            
            if (!Schema::hasColumn('project_pcb_files', 'file_size')) {
                $table->bigInteger('file_size')->nullable()->after('file_path');
            }
            
            if (!Schema::hasColumn('project_pcb_files', 'file_hash')) {
                $table->string('file_hash', 64)->nullable()->after('file_size');
            }
            
            if (!Schema::hasColumn('project_pcb_files', 'description')) {
                $table->text('description')->nullable()->after('version');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_pcb_files', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'file_size', 'file_hash', 'description']);
        });
    }
};