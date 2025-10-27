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
            $table->integer('total_boards_ordered')->default(0)->comment('Total boards from all quotations');
            $table->integer('boards_produced')->default(0)->comment('Boards physically produced');
            $table->integer('boards_assembled')->default(0)->comment('Boards assembled/tested');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['total_boards_ordered', 'boards_produced', 'boards_assembled']);
        });
    }
};
