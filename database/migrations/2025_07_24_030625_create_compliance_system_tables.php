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
        // Standard di conformità disponibili (CE, RoHS, FCC, etc.)
        Schema::create('compliance_standards', function (Blueprint $table) {
            $table->id();
            $table->string('code'); // CE, ROHS, FCC_ID, etc.
            $table->string('name'); // "Conformità Europea", "RoHS Directive"
            $table->text('description')->nullable();
            $table->string('issuing_authority')->nullable(); // "European Commission", "FCC"
            $table->string('geographic_scope')->nullable(); // "EU", "USA", "Global"
            $table->json('applicable_categories')->nullable(); // ["electronics", "medical", "automotive"]
            $table->json('required_tests')->nullable(); // ["EMC", "Safety", "RF"]
            $table->json('required_documentation')->nullable(); // ["Technical File", "DoC"]
            $table->string('validity_period')->nullable(); // "Permanent", "3 years", etc.
            $table->text('renewal_requirements')->nullable();
            $table->boolean('requires_testing')->default(true);
            $table->boolean('requires_declaration')->default(true);
            $table->string('template_path')->nullable(); // Path to template file
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Template per certificati e dichiarazioni
        Schema::create('compliance_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_standard_id')->constrained('compliance_standards')->onDelete('cascade');
            $table->string('name');
            $table->string('type'); // "declaration", "certificate", "technical_file"
            $table->text('description')->nullable();
            $table->json('required_fields'); // Campi richiesti nel template
            $table->text('template_content'); // Template in HTML/Markdown
            $table->json('ai_prompts')->nullable(); // Prompt per AI assistant
            $table->string('output_format')->default('pdf'); // pdf, docx, html
            $table->boolean('requires_ai_assistance')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Analisi AI per determinare conformità richieste
        Schema::create('compliance_ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('analyzable_type'); // Project, Component, etc.
            $table->unsignedBigInteger('analyzable_id');
            $table->json('input_data'); // Dati analizzati (componenti, specifiche, etc.)
            $table->json('ai_recommendations'); // Raccomandazioni AI
            $table->json('detected_standards'); // Standard rilevati automaticamente
            $table->json('risk_assessment')->nullable(); // Valutazione rischi compliance
            $table->text('ai_reasoning')->nullable(); // Spiegazione del ragionamento AI
            $table->float('confidence_score')->default(0); // 0-100 confidence
            $table->foreignId('analyzed_by')->constrained('users');
            $table->timestamp('analyzed_at');
            $table->timestamps();
            
            $table->index(['analyzable_type', 'analyzable_id']);
        });

        // Documenti di conformità generati per progetti
        Schema::create('project_compliance_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('compliance_standard_id')->constrained('compliance_standards');
            $table->foreignId('compliance_template_id')->nullable()->constrained('compliance_templates')->onDelete('set null');
            $table->string('document_type'); // "declaration", "certificate", "technical_file"
            $table->string('title');
            $table->string('document_number')->nullable(); // Numero documento ufficiale
            $table->text('description')->nullable();
            $table->json('compliance_data'); // Dati specifici per la conformità
            $table->string('file_path')->nullable(); // Path documento generato
            $table->string('file_format')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('status')->default('draft'); // draft, pending, approved, expired
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // Test e verifiche per conformità
        Schema::create('compliance_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_compliance_document_id')->constrained('project_compliance_documents')->onDelete('cascade');
            $table->string('test_type'); // "EMC", "Safety", "Environmental", etc.
            $table->string('test_standard'); // "EN 55032", "IEC 62368-1", etc.
            $table->text('description')->nullable();
            $table->string('test_lab')->nullable(); // Laboratorio che ha eseguito il test
            $table->string('test_report_number')->nullable();
            $table->json('test_results')->nullable(); // Risultati strutturati
            $table->string('status')->default('pending'); // pending, passed, failed, not_applicable
            $table->date('test_date')->nullable();
            $table->date('report_date')->nullable();
            $table->string('test_report_path')->nullable(); // Path al report
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Tracking scadenze e rinnovi
        Schema::create('compliance_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_compliance_document_id')->constrained('project_compliance_documents')->onDelete('cascade');
            $table->date('renewal_due_date');
            $table->string('renewal_type'); // "automatic", "testing_required", "documentation_update"
            $table->text('renewal_requirements')->nullable();
            $table->string('status')->default('pending'); // pending, in_progress, completed, overdue
            $table->date('reminder_sent_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_renewals');
        Schema::dropIfExists('compliance_tests');
        Schema::dropIfExists('project_compliance_documents');
        Schema::dropIfExists('compliance_ai_analyses');
        Schema::dropIfExists('compliance_templates');
        Schema::dropIfExists('compliance_standards');
    }
};