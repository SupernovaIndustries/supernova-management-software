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
        Schema::create('customer_contracts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('contract_number', 50)->unique();
            $table->string('title');
            $table->string('type', 50); // nda, service_agreement, supply_contract, partnership

            // Date
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('signed_at')->nullable();

            // Importi
            $table->decimal('contract_value', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');

            // File
            $table->text('nextcloud_path')->nullable();
            $table->timestamp('pdf_generated_at')->nullable();

            // Stato
            $table->string('status', 50)->default('draft'); // draft, active, expired, terminated

            // Note
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('customer_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_contracts');
    }
};
