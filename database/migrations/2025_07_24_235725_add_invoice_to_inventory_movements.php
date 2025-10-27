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
            $table->string('invoice_number')->nullable()->after('notes');
            $table->string('invoice_path')->nullable()->after('invoice_number');
            $table->date('invoice_date')->nullable()->after('invoice_path');
            $table->decimal('invoice_total', 10, 2)->nullable()->after('invoice_date');
            $table->string('supplier')->nullable()->after('invoice_total');
            
            $table->index('invoice_number');
            $table->index('invoice_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['invoice_number']);
            $table->dropIndex(['invoice_date']);
            
            $table->dropColumn([
                'invoice_number',
                'invoice_path', 
                'invoice_date',
                'invoice_total',
                'supplier'
            ]);
        });
    }
};