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
        Schema::create('payment_milestones', function (Blueprint $table) {
            $table->id();

            // Progetto/Preventivo
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->cascadeOnDelete();

            // Milestone
            $table->string('milestone_name'); // 'Acconto 30%', 'Saldo 70%', etc.
            $table->decimal('percentage', 5, 2);
            $table->decimal('amount', 12, 2);

            // Stato
            $table->string('status', 50)->default('pending'); // pending, invoiced, paid

            // Fattura associata
            $table->foreignId('invoice_id')->nullable()->constrained('invoices_issued')->nullOnDelete();

            // Date
            $table->date('expected_date')->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Ordinamento
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index('project_id');
            $table->index('quotation_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_milestones');
    }
};
