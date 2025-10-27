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
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('nextcloud_folder_created')->default(false)->after('folder');
            $table->text('nextcloud_base_path')->nullable()->after('nextcloud_folder_created');
            $table->boolean('components_tracked')->default(true)->after('nextcloud_base_path');
            $table->decimal('total_components_cost', 12, 2)->default(0)->after('components_tracked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'nextcloud_folder_created',
                'nextcloud_base_path',
                'components_tracked',
                'total_components_cost',
            ]);
        });
    }
};
