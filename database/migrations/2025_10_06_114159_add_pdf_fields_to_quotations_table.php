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
        Schema::table('quotations', function (Blueprint $table) {
            // PDF management
            $table->text('pdf_path')->nullable()->after('terms_conditions');
            $table->boolean('pdf_uploaded_manually')->default(false)->after('pdf_path');
            $table->timestamp('pdf_generated_at')->nullable()->after('pdf_uploaded_manually');

            // Nextcloud path for organization
            $table->text('nextcloud_path')->nullable()->after('pdf_generated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn([
                'pdf_path',
                'pdf_uploaded_manually',
                'pdf_generated_at',
                'nextcloud_path',
            ]);
        });
    }
};
