<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Indici per migliorare le performance delle query più comuni
        
        // Components - indici mancanti
        if (!DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'components_category_id_index'")) {
            Schema::table('components', function (Blueprint $table) {
                $table->index('category_id', 'components_category_id_index');
            });
        }
        
        if (!DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'components_status_index'")) {
            Schema::table('components', function (Blueprint $table) {
                $table->index('status', 'components_status_index');
            });
        }

        // Projects
        if (!DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'projects_customer_id_index'")) {
            Schema::table('projects', function (Blueprint $table) {
                $table->index('customer_id', 'projects_customer_id_index');
            });
        }
        
        if (!DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'projects_status_index'")) {
            Schema::table('projects', function (Blueprint $table) {
                $table->index('status', 'projects_status_index');
            });
        }

        // Customers
        if (!DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'customers_company_name_index'")) {
            Schema::table('customers', function (Blueprint $table) {
                $table->index('company_name', 'customers_company_name_index');
            });
        }

        // Quotations
        if (!DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'quotations_customer_id_index'")) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->index('customer_id', 'quotations_customer_id_index');
            });
        }
        
        if (!DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'quotations_project_id_index'")) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->index('project_id', 'quotations_project_id_index');
            });
        }

        // Project BOMs
        if (!DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'project_boms_project_id_index'")) {
            Schema::table('project_boms', function (Blueprint $table) {
                $table->index('project_id', 'project_boms_project_id_index');
            });
        }
        
        // Project BOM Items
        if (!DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'project_bom_items_project_bom_id_index'")) {
            Schema::table('project_bom_items', function (Blueprint $table) {
                $table->index('project_bom_id', 'project_bom_items_project_bom_id_index');
            });
        }
        
        if (!DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'project_bom_items_component_id_index'")) {
            Schema::table('project_bom_items', function (Blueprint $table) {
                $table->index('component_id', 'project_bom_items_component_id_index');
            });
        }

        // Inventory movements
        if (!DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'inventory_movements_component_id_index'")) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->index('component_id', 'inventory_movements_component_id_index');
            });
        }
        
        if (!DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'inventory_movements_type_index'")) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->index('type', 'inventory_movements_type_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop degli indici se esistono
        $indexes = [
            'components' => ['components_category_id_index', 'components_status_index'],
            'projects' => ['projects_customer_id_index', 'projects_status_index'],
            'customers' => ['customers_company_name_index'],
            'quotations' => ['quotations_customer_id_index', 'quotations_project_id_index'],
            'project_boms' => ['project_boms_project_id_index'],
            'project_bom_items' => ['project_bom_items_project_bom_id_index', 'project_bom_items_component_id_index'],
            'inventory_movements' => ['inventory_movements_component_id_index', 'inventory_movements_type_index']
        ];

        foreach ($indexes as $table => $tableIndexes) {
            Schema::table($table, function (Blueprint $table) use ($tableIndexes) {
                foreach ($tableIndexes as $index) {
                    if (DB::select("SELECT 1 FROM pg_indexes WHERE indexname = ?", [$index])) {
                        $table->dropIndex($index);
                    }
                }
            });
        }
    }
};
