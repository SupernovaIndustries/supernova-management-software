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
        // Template datasheet configurabili
        Schema::create('datasheet_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('project'); // project, component, system
            $table->text('description')->nullable();
            $table->json('sections'); // Sezioni configurabili del datasheet
            $table->json('styles')->nullable(); // CSS/styling personalizzato
            $table->string('logo_path')->nullable();
            $table->boolean('include_company_info')->default(true);
            $table->boolean('include_toc')->default(true); // Table of contents
            $table->string('output_format')->default('pdf'); // pdf, html, markdown
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Datasheet generati e salvati
        Schema::create('generated_datasheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('datasheet_template_id')->constrained('datasheet_templates')->onDelete('cascade');
            $table->string('generatable_type'); // Project, Component, etc.
            $table->unsignedBigInteger('generatable_id');
            $table->string('title');
            $table->string('version')->default('1.0');
            $table->text('description')->nullable();
            $table->json('generated_data'); // Dati utilizzati per generazione
            $table->string('file_path'); // Path del file generato
            $table->string('file_format'); // pdf, html, markdown
            $table->bigInteger('file_size')->nullable();
            $table->string('file_hash')->nullable();
            $table->foreignId('generated_by')->constrained('users');
            $table->timestamp('generated_at');
            $table->timestamps();
            
            $table->index(['generatable_type', 'generatable_id']);
        });

        // Sezioni predefinite per template
        Schema::create('datasheet_template_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // overview, specifications, schematic, etc.
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('section_type'); // text, table, image, specs, bom, etc.
            $table->json('default_config')->nullable(); // Configurazione default
            $table->string('template_blade')->nullable(); // Nome template Blade
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Dati personalizzati per progetti (datasheet override)
        Schema::create('project_datasheet_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->json('custom_specifications')->nullable(); // Specs override
            $table->text('overview_text')->nullable();
            $table->text('features_list')->nullable();
            $table->text('applications')->nullable();
            $table->json('technical_drawings')->nullable(); // Paths to images
            $table->json('performance_data')->nullable(); // Charts, tables
            $table->string('target_market')->nullable();
            $table->text('compliance_standards')->nullable(); // CE, RoHS, etc.
            $table->timestamps();
        });

        // Dati personalizzati per componenti (datasheet override)
        Schema::create('component_datasheet_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained('components')->onDelete('cascade');
            $table->json('electrical_specs')->nullable(); // Voltage, current, etc.
            $table->json('mechanical_specs')->nullable(); // Dimensions, weight
            $table->json('environmental_specs')->nullable(); // Temperature, humidity
            $table->text('pin_configuration')->nullable();
            $table->text('application_notes')->nullable();
            $table->json('recommended_pcb_layout')->nullable();
            $table->string('package_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('component_datasheet_data');
        Schema::dropIfExists('project_datasheet_data');
        Schema::dropIfExists('datasheet_template_sections');
        Schema::dropIfExists('generated_datasheets');
        Schema::dropIfExists('datasheet_templates');
    }
};