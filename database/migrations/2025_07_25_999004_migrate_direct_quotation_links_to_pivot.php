<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, migrate existing direct quotation-project links to pivot table
        $quotationsWithProjects = DB::table('quotations')
            ->whereNotNull('project_id')
            ->select('id', 'project_id')
            ->get();

        foreach ($quotationsWithProjects as $quotation) {
            // Insert into pivot table if not already exists
            DB::table('project_quotation')->insertOrIgnore([
                'project_id' => $quotation->project_id,
                'quotation_id' => $quotation->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Remove the direct project_id foreign key constraint first
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        // Keep the project_id field for backward compatibility for now
        // but make it nullable to avoid required constraint issues
        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->change();
        });
        
        // Add a comment to indicate this field is deprecated (PostgreSQL syntax)
        DB::statement('COMMENT ON COLUMN quotations.project_id IS \'DEPRECATED: Use project_quotation pivot table instead\'');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore foreign key constraint
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
        });
        
        // Remove comment (PostgreSQL syntax)
        DB::statement('COMMENT ON COLUMN quotations.project_id IS NULL');
    }
};
