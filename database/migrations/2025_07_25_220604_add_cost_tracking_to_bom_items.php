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
        Schema::table('project_bom_items', function (Blueprint $table) {
            $table->decimal('estimated_unit_cost', 10, 4)->nullable()->after('allocated');
            $table->decimal('actual_unit_cost', 10, 4)->nullable()->after('estimated_unit_cost');
            $table->decimal('total_estimated_cost', 10, 4)->nullable()->after('actual_unit_cost');
            $table->decimal('total_actual_cost', 10, 4)->nullable()->after('total_estimated_cost');
            $table->string('cost_source')->nullable()->after('total_actual_cost')->comment('manual, inventory, supplier_api');
            $table->timestamp('cost_updated_at')->nullable()->after('cost_source');
            
            $table->index(['allocated', 'actual_unit_cost']);
            $table->index('cost_updated_at');
        });
        
        // Add cost summary fields to project_boms
        Schema::table('project_boms', function (Blueprint $table) {
            $table->decimal('total_estimated_cost', 10, 2)->nullable()->after('processed_by');
            $table->decimal('total_actual_cost', 10, 2)->nullable()->after('total_estimated_cost');
            $table->decimal('cost_variance', 10, 2)->nullable()->after('total_actual_cost');
            $table->decimal('cost_variance_percentage', 5, 2)->nullable()->after('cost_variance');
            $table->timestamp('costs_calculated_at')->nullable()->after('cost_variance_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_bom_items', function (Blueprint $table) {
            $table->dropIndex(['allocated', 'actual_unit_cost']);
            $table->dropIndex(['cost_updated_at']);
            
            $table->dropColumn([
                'estimated_unit_cost',
                'actual_unit_cost', 
                'total_estimated_cost',
                'total_actual_cost',
                'cost_source',
                'cost_updated_at'
            ]);
        });
        
        Schema::table('project_boms', function (Blueprint $table) {
            $table->dropColumn([
                'total_estimated_cost',
                'total_actual_cost',
                'cost_variance',
                'cost_variance_percentage',
                'costs_calculated_at'
            ]);
        });
    }
};