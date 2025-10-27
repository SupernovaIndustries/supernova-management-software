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
        Schema::table('components', function (Blueprint $table) {
            // Aggiungi solo le colonne che non esistono ancora
            if (!Schema::hasColumn('components', 'invoice_reference')) {
                $table->string('invoice_reference')->nullable()->after('currency');
            }
            if (!Schema::hasColumn('components', 'invoice_pdf_path')) {
                $table->string('invoice_pdf_path')->nullable()->after('invoice_reference');
            }
            if (!Schema::hasColumn('components', 'purchase_date')) {
                $table->date('purchase_date')->nullable()->after('invoice_pdf_path');
            }
            if (!Schema::hasColumn('components', 'supplier')) {
                $table->string('supplier')->nullable()->after('purchase_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            // Elimina solo le colonne che abbiamo aggiunto in questa migrazione
            if (Schema::hasColumn('components', 'invoice_reference')) {
                $table->dropColumn('invoice_reference');
            }
            if (Schema::hasColumn('components', 'invoice_pdf_path')) {
                $table->dropColumn('invoice_pdf_path');
            }
            if (Schema::hasColumn('components', 'purchase_date')) {
                $table->dropColumn('purchase_date');
            }
            if (Schema::hasColumn('components', 'supplier')) {
                $table->dropColumn('supplier');
            }
        });
    }
};
