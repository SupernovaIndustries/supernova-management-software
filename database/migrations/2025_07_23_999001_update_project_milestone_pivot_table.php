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
        // Prima verifichiamo se la tabella esiste già
        if (!Schema::hasTable('project_milestone')) {
            Schema::create('project_milestone', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->onDelete('cascade');
                $table->foreignId('milestone_id')->constrained()->onDelete('cascade');
                $table->date('target_date')->nullable();
                $table->date('completed_date')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_completed')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                
                $table->unique(['project_id', 'milestone_id']);
                $table->index('project_id');
                $table->index('milestone_id');
            });
        } else {
            // Se esiste, aggiungiamo solo i campi mancanti
            Schema::table('project_milestone', function (Blueprint $table) {
                if (!Schema::hasColumn('project_milestone', 'is_completed')) {
                    $table->boolean('is_completed')->default(false)->after('notes');
                }
                if (!Schema::hasColumn('project_milestone', 'sort_order')) {
                    $table->integer('sort_order')->default(0)->after('is_completed');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_milestone', function (Blueprint $table) {
            if (Schema::hasColumn('project_milestone', 'is_completed')) {
                $table->dropColumn('is_completed');
            }
            if (Schema::hasColumn('project_milestone', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};