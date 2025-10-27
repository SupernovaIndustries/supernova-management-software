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
        Schema::create('invoices_received', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 100);

            // Fornitore
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('supplier_name');
            $table->string('supplier_vat', 50)->nullable();

            // Tipo e categoria
            $table->string('type', 50)->default('purchase'); // purchase, customs, equipment, general, restock
            $table->string('category', 50)->default('components'); // components, equipment, services, customs, general

            // Link a progetto/cliente (se fattura per progetto specifico)
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // Dati fattura
            $table->date('issue_date');
            $table->date('due_date');
            $table->date('received_date')->nullable();

            // Importi
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->nullable()->default(0);
            $table->decimal('total', 12, 2);
            $table->string('currency', 3)->default('EUR');

            // Pagamento
            $table->string('payment_status', 50)->default('unpaid');
            $table->decimal('amount_paid', 12, 2)->nullable()->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 50)->nullable();

            // Nextcloud
            $table->text('nextcloud_path')->nullable();

            // Note
            $table->text('notes')->nullable();

            // Metadati
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('supplier_id');
            $table->index('project_id');
            $table->index('type');
            $table->index('issue_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices_received');
    }
};
