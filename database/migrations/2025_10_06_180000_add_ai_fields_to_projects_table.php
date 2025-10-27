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
            // Completion percentage based on milestones
            $table->decimal('completion_percentage', 5, 2)->default(0)->after('progress');

            // AI-calculated priority score (1-100)
            $table->integer('ai_priority_score')->nullable()->after('priority_id');

            // Store AI priority calculation data
            $table->json('ai_priority_data')->nullable()->after('ai_priority_score');

            // Timestamp of last AI priority calculation
            $table->timestamp('ai_priority_calculated_at')->nullable()->after('ai_priority_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'completion_percentage',
                'ai_priority_score',
                'ai_priority_data',
                'ai_priority_calculated_at',
            ]);
        });
    }
};
