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
        // Add Nextcloud fields to documents table
        Schema::table('documents', function (Blueprint $table) {
            $table->string('nextcloud_path')->nullable()->after('syncthing_path');
            $table->boolean('uploaded_to_nextcloud')->default(false)->after('nextcloud_path');
            $table->boolean('local_file_deleted')->default(false)->after('uploaded_to_nextcloud');
        });

        // Add Nextcloud fields to project_documents table
        Schema::table('project_documents', function (Blueprint $table) {
            $table->string('nextcloud_path')->nullable()->after('file_path');
            $table->boolean('uploaded_to_nextcloud')->default(false)->after('nextcloud_path');
            $table->boolean('local_file_deleted')->default(false)->after('uploaded_to_nextcloud');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['nextcloud_path', 'uploaded_to_nextcloud', 'local_file_deleted']);
        });

        Schema::table('project_documents', function (Blueprint $table) {
            $table->dropColumn(['nextcloud_path', 'uploaded_to_nextcloud', 'local_file_deleted']);
        });
    }
};
