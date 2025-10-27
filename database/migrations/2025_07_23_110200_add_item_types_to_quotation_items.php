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
        Schema::table('quotation_items', function (Blueprint $table) {
            if (!Schema::hasColumn('quotation_items', 'item_type')) {
                $table->string('item_type')->default('custom')->after('quotation_id');
            }
            if (!Schema::hasColumn('quotation_items', 'hours')) {
                $table->decimal('hours', 8, 2)->nullable()->after('quantity');
            }
            if (!Schema::hasColumn('quotation_items', 'hourly_rate')) {
                $table->decimal('hourly_rate', 8, 2)->nullable()->after('hours');
            }
            if (!Schema::hasColumn('quotation_items', 'material_cost')) {
                $table->decimal('material_cost', 10, 2)->nullable()->after('hourly_rate');
            }
            if (!Schema::hasColumn('quotation_items', 'is_from_inventory')) {
                $table->boolean('is_from_inventory')->default(false)->after('material_cost');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn([
                'item_type',
                'hours',
                'hourly_rate',
                'material_cost',
                'is_from_inventory'
            ]);
        });
    }
};