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
            $table->json('ai_analysis_data')->nullable()->after('notes');
            $table->json('ai_extracted_parties')->nullable()->after('ai_analysis_data');
            $table->json('ai_risk_flags')->nullable()->after('ai_extracted_parties');
            $table->json('ai_key_dates')->nullable()->after('ai_risk_flags');
            $table->timestamp('ai_analyzed_at')->nullable()->after('ai_key_dates');

            $table->index('ai_analyzed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_contracts', function (Blueprint $table) {
            $table->dropIndex(['ai_analyzed_at']);
            $table->dropColumn([
                'ai_analysis_data',
                'ai_extracted_parties',
                'ai_risk_flags',
                'ai_key_dates',
                'ai_analyzed_at',
            ]);
        });
    }
};
