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
        Schema::table('invoices_issued', function (Blueprint $table) {
            // Link to specific payment term tranche
            $table->foreignId('payment_term_tranche_id')
                ->nullable()
                ->after('payment_term_id')
                ->constrained('payment_term_tranches')
                ->nullOnDelete();

            // Add index
            $table->index('payment_term_tranche_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices_issued', function (Blueprint $table) {
            $table->dropForeign(['payment_term_tranche_id']);
            $table->dropIndex(['payment_term_tranche_id']);
            $table->dropColumn('payment_term_tranche_id');
        });
    }
};
