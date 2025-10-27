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
        Schema::create('invoice_issued_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices_issued')->cascadeOnDelete();

            // Descrizione
            $table->text('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_percentage', 5, 2)->nullable()->default(0);
            $table->decimal('tax_rate', 5, 2)->default(22.00);

            // Totali
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('total', 12, 2);

            // Link opzionali
            $table->foreignId('component_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_bom_item_id')->nullable()->constrained()->nullOnDelete();

            // Ordinamento
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_issued_items');
    }
};
