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
            $table->string('sdi_code', 7)->nullable()->after('tax_code');
            $table->string('pec_email')->nullable()->after('email');
            $table->dropColumn('credit_limit');
            $table->dropColumn('payment_terms');
            $table->renameColumn('syncthing_folder', 'folder');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('sdi_code');
            $table->dropColumn('pec_email');
            $table->decimal('credit_limit', 10, 2)->nullable();
            $table->string('payment_terms')->nullable();
            $table->renameColumn('folder', 'syncthing_folder');
        });
    }
};