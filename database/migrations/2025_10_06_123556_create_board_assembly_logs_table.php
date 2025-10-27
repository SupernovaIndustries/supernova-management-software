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
        Schema::create('board_assembly_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->date('assembly_date')->comment('Date when boards were assembled');
            $table->integer('boards_count')->comment('Number of boards assembled in this session');
            $table->string('batch_number')->nullable()->comment('Optional batch/lot number');
            $table->text('notes')->nullable()->comment('Assembly notes, issues, observations');
            $table->string('status')->default('assembled')->comment('assembled, tested, failed, rework');
            $table->timestamps();

            $table->index('project_id');
            $table->index('assembly_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('board_assembly_logs');
    }
};
