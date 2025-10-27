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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('user_id')->constrained(); // Project manager
            $table->string('status')->default('planning'); // planning, in_progress, completed, cancelled
            $table->string('priority')->default('medium'); // low, medium, high, urgent
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->decimal('actual_cost', 12, 2)->nullable();
            $table->integer('progress')->default(0); // 0-100
            $table->json('milestones')->nullable();
            $table->text('notes')->nullable();
            $table->string('syncthing_folder')->nullable(); // Project folder in Syncthing
            $table->timestamps();
            
            $table->index(['code', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};