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
        Schema::table('customers', function (Blueprint $table) {
            // Only add company_name if it doesn't exist
            if (!Schema::hasColumn('customers', 'company_name')) {
                $table->string('company_name')->nullable()->after('code');
            }
            
            // Drop name column if it exists
            if (Schema::hasColumn('customers', 'name')) {
                $table->dropColumn('name');
            }
            
            // Remove old type column if it exists
            if (Schema::hasColumn('customers', 'type')) {
                $table->dropColumn('type');
            }
            
            // Add customer type relationship
            $table->foreignId('customer_type_id')->nullable()->constrained()->onDelete('set null');
            
            // Add payment terms relationship
            $table->foreignId('payment_term_id')->nullable()->constrained()->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['customer_type_id']);
            $table->dropForeign(['payment_term_id']);
            $table->dropColumn(['company_name', 'customer_type_id', 'payment_term_id']);
            $table->string('name')->after('code');
        });
    }
};
