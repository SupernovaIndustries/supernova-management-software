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
        Schema::create('invoices_issued', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->integer('incremental_id');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();

            // Tipo fattura
            $table->string('type', 50)->default('standard'); // standard, advance_payment, balance, credit_note

            // Dati fattura
            $table->date('issue_date');
            $table->date('due_date');
            $table->foreignId('payment_term_id')->nullable()->constrained()->nullOnDelete();

            // Importi
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(22.00);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->nullable()->default(0);
            $table->decimal('total', 12, 2);

            // Pagamento progressivo (es: 30% + 70%)
            $table->string('payment_stage', 50)->nullable(); // 'deposit', 'balance', 'full'
            $table->decimal('payment_percentage', 5, 2)->nullable(); // 30.00, 70.00, 100.00
            $table->foreignId('related_invoice_id')->nullable()->constrained('invoices_issued')->nullOnDelete(); // Link a fattura correlata

            // Stati
            $table->string('status', 50)->default('draft'); // draft, sent, paid, overdue, cancelled
            $table->string('payment_status', 50)->default('unpaid'); // unpaid, partial, paid

            // Pagamenti
            $table->decimal('amount_paid', 12, 2)->nullable()->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 50)->nullable();

            // Nextcloud
            $table->text('nextcloud_path')->nullable();
            $table->timestamp('pdf_generated_at')->nullable();

            // Note
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            // Metadati
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('customer_id');
            $table->index('project_id');
            $table->index('issue_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices_issued');
    }
};
