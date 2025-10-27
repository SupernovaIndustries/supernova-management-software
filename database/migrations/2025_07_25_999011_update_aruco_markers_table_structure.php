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
        Schema::table('aruco_markers', function (Blueprint $table) {
            // Drop old columns and their constraints first
            $table->dropForeign(['last_scanned_by']);
            $table->dropIndex(['trackable_type', 'trackable_id']);
            $table->dropColumn(['trackable_type', 'trackable_id', 'metadata', 'last_scanned_by']);
            
            // Add new columns
            $table->string('name')->nullable()->after('type');
            $table->text('description')->nullable()->after('name');
            $table->json('data')->nullable()->after('description');
            $table->foreignId('component_id')->nullable()->constrained()->onDelete('cascade')->after('data');
            $table->foreignId('project_id')->nullable()->constrained()->onDelete('cascade')->after('component_id');
            $table->integer('scan_count')->default(0)->after('last_scanned_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->after('scan_count');
            
            // Add new indexes (skip unique marker_id as it already exists)
            $table->index(['marker_id', 'type']);
            $table->index(['type', 'is_active']);
            $table->index('last_scanned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aruco_markers', function (Blueprint $table) {
            // Drop new columns
            $table->dropForeign(['component_id']);
            $table->dropForeign(['project_id']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['name', 'description', 'data', 'component_id', 'project_id', 'scan_count', 'created_by']);
            
            // Add back old columns
            $table->string('trackable_type')->nullable();
            $table->unsignedBigInteger('trackable_id')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('last_scanned_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Drop unique constraint
            $table->dropUnique(['marker_id']);
        });
    }
};
