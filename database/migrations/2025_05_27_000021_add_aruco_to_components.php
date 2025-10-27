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
        Schema::table('components', function (Blueprint $table) {
            $table->string('aruco_code')->nullable()->unique()->after('datasheet_url');
            $table->string('aruco_image_path')->nullable()->after('aruco_code');
            $table->timestamp('aruco_generated_at')->nullable()->after('aruco_image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->dropColumn(['aruco_code', 'aruco_image_path', 'aruco_generated_at']);
        });
    }
};