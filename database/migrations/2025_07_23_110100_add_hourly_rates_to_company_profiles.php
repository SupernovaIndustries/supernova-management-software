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
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->decimal('hourly_rate_design', 8, 2)->default(50.00)->after('notes');
            $table->decimal('hourly_rate_assembly', 8, 2)->default(50.00)->after('hourly_rate_design');
            $table->decimal('pcb_standard_cost', 8, 2)->default(200.00)->after('hourly_rate_assembly');
            $table->integer('pcb_standard_quantity', false, true)->default(5)->after('pcb_standard_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'hourly_rate_design',
                'hourly_rate_assembly', 
                'pcb_standard_cost',
                'pcb_standard_quantity'
            ]);
        });
    }
};