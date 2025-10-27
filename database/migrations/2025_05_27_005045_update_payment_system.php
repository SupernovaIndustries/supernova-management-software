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
        // Update projects table
        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('syncthing_folder', 'folder');
            $table->string('project_status')->nullable()->after('status'); // prototipo_test, consegna_prototipo, etc
        });

        // Update quotations table - add payment details
        Schema::table('quotations', function (Blueprint $table) {
            $table->decimal('materials_deposit', 12, 2)->nullable()->after('total');
            $table->decimal('development_balance', 12, 2)->nullable()->after('materials_deposit');
            $table->string('payment_status')->default('pending')->after('status'); // pending, deposit_paid, paid
            $table->timestamp('deposit_paid_at')->nullable();
            $table->timestamp('balance_paid_at')->nullable();
        });

        // Update documents table
        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('syncthing_path', 'folder_path');
        });

        // Create BOM table
        Schema::create('project_boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->string('file_path')->nullable();
            $table->string('folder_path')->nullable();
            $table->json('components_data')->nullable(); // Parsed BOM data
            $table->string('status')->default('pending'); // pending, processed, allocated
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->timestamps();
            
            $table->index('project_id');
        });

        // Create BOM items table
        Schema::create('project_bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_bom_id')->constrained('project_boms')->onDelete('cascade');
            $table->foreignId('component_id')->nullable()->constrained();
            $table->string('reference'); // Component reference from BOM (C1, R2, etc)
            $table->string('value')->nullable();
            $table->string('footprint')->nullable();
            $table->string('manufacturer_part')->nullable();
            $table->integer('quantity');
            $table->boolean('allocated')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['project_bom_id', 'allocated']);
        });

        // Create PCB files table
        Schema::create('project_pcb_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->string('file_name');
            $table->string('file_type'); // kicad, altium, eagle, gerber
            $table->string('folder_path');
            $table->string('version')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_pcb_files');
        Schema::dropIfExists('project_bom_items');
        Schema::dropIfExists('project_boms');

        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('folder_path', 'syncthing_path');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['materials_deposit', 'development_balance', 'payment_status', 'deposit_paid_at', 'balance_paid_at']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('folder', 'syncthing_folder');
            $table->dropColumn('project_status');
        });
    }
};