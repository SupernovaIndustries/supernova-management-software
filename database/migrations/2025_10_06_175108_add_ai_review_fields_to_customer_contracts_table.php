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
        Schema::table('customer_contracts', function (Blueprint $table) {
            $table->json('ai_review_data')->nullable()->after('notes');
            $table->integer('ai_review_score')->nullable()->after('ai_review_data');
            $table->integer('ai_review_issues_count')->default(0)->after('ai_review_score');
            $table->timestamp('ai_reviewed_at')->nullable()->after('ai_review_issues_count');

            // Add indexes for performance
            $table->index('ai_review_score');
            $table->index('ai_reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_contracts', function (Blueprint $table) {
            $table->dropIndex(['ai_review_score']);
            $table->dropIndex(['ai_reviewed_at']);

            $table->dropColumn([
                'ai_review_data',
                'ai_review_score',
                'ai_review_issues_count',
                'ai_reviewed_at',
            ]);
        });
    }
};
