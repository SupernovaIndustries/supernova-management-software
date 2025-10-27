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
        Schema::table('board_assembly_logs', function (Blueprint $table) {
            $table->boolean('is_prototype')->default(false)->after('status');
            $table->index('is_prototype');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('board_assembly_logs', function (Blueprint $table) {
            $table->dropIndex(['is_prototype']);
            $table->dropColumn('is_prototype');
        });
    }
};
