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
        Schema::table('project_boms', function (Blueprint $table) {
            $table->string('nextcloud_path')->nullable()->after('folder_path');
            $table->string('uploaded_file_path')->nullable()->after('nextcloud_path');

            $table->index('nextcloud_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_boms', function (Blueprint $table) {
            $table->dropIndex(['nextcloud_path']);
            $table->dropColumn(['nextcloud_path', 'uploaded_file_path']);
        });
    }
};
