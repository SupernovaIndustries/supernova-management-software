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
        Schema::table('assembly_checklists', function (Blueprint $table) {
            $table->foreignId('board_assembly_log_id')->nullable()->after('project_id')->constrained('board_assembly_logs')->onDelete('cascade');
            $table->index('board_assembly_log_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assembly_checklists', function (Blueprint $table) {
            $table->dropForeign(['board_assembly_log_id']);
            $table->dropColumn('board_assembly_log_id');
        });
    }
};
