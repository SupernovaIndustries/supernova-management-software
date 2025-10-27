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
        // Categorie di sistemi (IMU, GPS, Cellular, Power, etc.)
        Schema::create('system_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // IMU_SENSORS, GPS_MODULES, CELLULAR_MODULES
            $table->string('display_name'); // "Sensori IMU", "Moduli GPS"
            $table->text('description')->nullable();
            $table->string('icon')->default('heroicon-o-cog');
            $table->string('color')->default('primary');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Varianti per ogni categoria (6-axis, 9-axis, etc.)
        Schema::create('system_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_category_id')->constrained('system_categories')->onDelete('cascade');
            $table->string('name'); // IMU_6AXIS, IMU_9AXIS
            $table->string('display_name'); // "IMU 6-assi", "IMU 9-assi"
            $table->text('description')->nullable();
            $table->json('specifications')->nullable(); // Specs tecniche specifiche
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Fasi del processo (Progettazione, Layout, Test, Assemblaggio)
        Schema::create('system_phases', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // DESIGN, LAYOUT, TEST, ASSEMBLY
            $table->string('display_name'); // "Progettazione", "Layout PCB"
            $table->text('description')->nullable();
            $table->string('color')->default('blue');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Template checklist per variante + fase
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_variant_id')->constrained('system_variants')->onDelete('cascade');
            $table->foreignId('system_phase_id')->constrained('system_phases')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Singoli item di checklist per template
        Schema::create('checklist_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_template_id')->constrained('checklist_templates')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('notes')->nullable(); // Note tecniche specifiche
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->boolean('is_blocking')->default(false); // Blocca avanzamento se non completato
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Istanze di sistemi per progetto (collegate alla BOM)
        Schema::create('project_system_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('system_variant_id')->constrained('system_variants')->onDelete('cascade');
            $table->foreignId('component_id')->nullable()->constrained('components')->onDelete('set null'); // Componente BOM che ha triggerato
            $table->string('instance_name'); // "Sistema IMU Principale", "GPS Backup"
            $table->text('custom_notes')->nullable();
            $table->json('custom_specifications')->nullable(); // Override specs per questo progetto
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Progress checklist per istanza progetto
        Schema::create('project_checklist_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_system_instance_id')->constrained('project_system_instances')->onDelete('cascade');
            $table->foreignId('checklist_template_item_id')->constrained('checklist_template_items')->onDelete('cascade');
            $table->foreignId('system_phase_id')->constrained('system_phases')->onDelete('cascade');
            $table->boolean('is_completed')->default(false);
            $table->text('completion_notes')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('completed_at')->nullable();
            $table->text('custom_notes')->nullable(); // Note specifiche per questo progetto
            $table->timestamps();
            
            // Evita duplicati
            $table->unique(['project_system_instance_id', 'checklist_template_item_id'], 'project_checklist_unique');
        });

        // Collegamento automatico componenti → varianti di sistema
        Schema::create('component_system_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained('components')->onDelete('cascade');
            $table->foreignId('system_variant_id')->constrained('system_variants')->onDelete('cascade');
            $table->boolean('is_auto_detected')->default(true); // Se rilevato automaticamente
            $table->integer('confidence_score')->default(100); // 0-100 affidabilità auto-detection
            $table->timestamps();
            
            $table->unique(['component_id', 'system_variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('component_system_mappings');
        Schema::dropIfExists('project_checklist_progress');
        Schema::dropIfExists('project_system_instances');
        Schema::dropIfExists('checklist_template_items');
        Schema::dropIfExists('checklist_templates');
        Schema::dropIfExists('system_phases');
        Schema::dropIfExists('system_variants');
        Schema::dropIfExists('system_categories');
    }
};