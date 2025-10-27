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
        Schema::create('billing_analysis', function (Blueprint $table) {
            $table->id();

            // Periodo
            $table->string('analysis_type', 50); // monthly, quarterly, yearly, custom
            $table->date('period_start');
            $table->date('period_end');

            // Ricavi
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('total_invoiced', 12, 2)->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->decimal('total_outstanding', 12, 2)->default(0);

            // Costi
            $table->decimal('total_costs', 12, 2)->default(0);
            $table->decimal('warehouse_costs', 12, 2)->default(0);
            $table->decimal('equipment_costs', 12, 2)->default(0);
            $table->decimal('service_costs', 12, 2)->default(0);
            $table->decimal('customs_costs', 12, 2)->default(0);

            // Profitto
            $table->decimal('gross_profit', 12, 2)->default(0);
            $table->decimal('net_profit', 12, 2)->default(0);
            $table->decimal('profit_margin', 5, 2)->default(0);

            // Previsioni
            $table->decimal('forecasted_revenue', 12, 2)->default(0);
            $table->decimal('forecasted_costs', 12, 2)->default(0);

            // Dettagli JSON
            $table->json('details')->nullable();

            // Generazione
            $table->timestamp('generated_at')->useCurrent();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index(['period_start', 'period_end']);
            $table->index('analysis_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_analysis');
    }
};
