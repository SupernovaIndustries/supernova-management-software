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
        Schema::table('inventory_movements', function (Blueprint $table) {
            // Aggiungi colonne per tracciabilità fatture
            $table->foreignId('source_invoice_id')->nullable()->after('supplier')->constrained('invoices_received')->nullOnDelete();
            $table->foreignId('destination_project_id')->nullable()->after('source_invoice_id')->constrained('projects')->nullOnDelete();
            $table->foreignId('allocation_id')->nullable()->after('destination_project_id')->constrained('project_component_allocations')->nullOnDelete();
            $table->decimal('total_cost', 12, 2)->nullable()->after('unit_cost');

            // Indexes
            $table->index('source_invoice_id');
            $table->index('destination_project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['source_invoice_id']);
            $table->dropForeign(['destination_project_id']);
            $table->dropForeign(['allocation_id']);
            $table->dropIndex(['source_invoice_id']);
            $table->dropIndex(['destination_project_id']);
            $table->dropColumn(['source_invoice_id', 'destination_project_id', 'allocation_id', 'total_cost']);
        });
    }
};
