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
        Schema::table('customer_types', function (Blueprint $table) {
            // Add color field if it doesn't exist
            if (!Schema::hasColumn('customer_types', 'color')) {
                $table->string('color')->default('blue')->after('description');
            }
            
            // Rename active to is_active if active column exists
            if (Schema::hasColumn('customer_types', 'active') && !Schema::hasColumn('customer_types', 'is_active')) {
                $table->renameColumn('active', 'is_active');
            } else if (!Schema::hasColumn('customer_types', 'is_active') && !Schema::hasColumn('customer_types', 'active')) {
                // Add is_active if neither exists
                $table->boolean('is_active')->default(true);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_types', function (Blueprint $table) {
            if (Schema::hasColumn('customer_types', 'color')) {
                $table->dropColumn('color');
            }
            
            if (Schema::hasColumn('customer_types', 'is_active') && !Schema::hasColumn('customer_types', 'active')) {
                $table->renameColumn('is_active', 'active');
            }
        });
    }
};
