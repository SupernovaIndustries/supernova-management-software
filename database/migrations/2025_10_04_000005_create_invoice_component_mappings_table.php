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
        Schema::create('invoice_component_mappings', function (Blueprint $table) {
            $table->id();

            // Fattura ricevuta
            $table->foreignId('invoice_received_id')->constrained('invoices_received')->cascadeOnDelete();
            $table->foreignId('invoice_received_item_id')->nullable()->constrained('invoice_received_items')->cascadeOnDelete();

            // Componente
            $table->foreignId('component_id')->constrained()->restrictOnDelete();

            // Quantità acquistata
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_cost', 12, 2);

            // Movimento magazzino associato
            $table->foreignId('inventory_movement_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('invoice_received_id');
            $table->index('component_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_component_mappings');
    }
};
