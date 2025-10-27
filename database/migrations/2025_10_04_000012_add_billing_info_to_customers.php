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
            // Aggiungi info fatturazione
            $table->string('billing_email')->nullable()->after('pec_email');
            $table->string('billing_contact_name')->nullable()->after('billing_email');
            $table->string('billing_phone', 50)->nullable()->after('billing_contact_name');
            $table->string('default_payment_terms', 100)->nullable()->after('billing_phone');
            $table->decimal('credit_limit', 12, 2)->nullable()->after('default_payment_terms');
            $table->decimal('current_balance', 12, 2)->default(0)->after('credit_limit');
            $table->boolean('nextcloud_folder_created')->default(false)->after('current_balance');
            $table->text('nextcloud_base_path')->nullable()->after('nextcloud_folder_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'billing_email',
                'billing_contact_name',
                'billing_phone',
                'default_payment_terms',
                'credit_limit',
                'current_balance',
                'nextcloud_folder_created',
                'nextcloud_base_path',
            ]);
        });
    }
};
