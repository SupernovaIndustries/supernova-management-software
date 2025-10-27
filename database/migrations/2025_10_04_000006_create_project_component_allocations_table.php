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
        Schema::create('project_component_allocations', function (Blueprint $table) {
            $table->id();

            // Progetto e componente
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_id')->constrained()->restrictOnDelete();

            // Quantità
            $table->decimal('quantity_allocated', 10, 2);
            $table->decimal('quantity_used', 10, 2)->default(0);
            $table->decimal('quantity_remaining', 10, 2)->nullable();

            // BOM item associato
            $table->foreignId('project_bom_item_id')->nullable()->constrained()->nullOnDelete();

            // Stato
            $table->string('status', 50)->default('allocated'); // allocated, in_use, completed, returned

            // Costi
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();

            // Tracciabilità: da quale fattura vengono questi componenti
            $table->foreignId('source_invoice_id')->nullable()->constrained('invoices_received')->nullOnDelete();

            // Date
            $table->timestamp('allocated_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            // Note
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('project_id');
            $table->index('component_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_component_allocations');
    }
};
