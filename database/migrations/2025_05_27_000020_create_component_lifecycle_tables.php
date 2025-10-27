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
        // Component Lifecycle Status
        Schema::create('component_lifecycle_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->onDelete('cascade');
            $table->enum('lifecycle_stage', [
                'active', 
                'nrnd', // Not Recommended for New Designs
                'eol_announced', // End of Life Announced
                'eol', // End of Life
                'obsolete'
            ])->default('active');
            $table->date('eol_announcement_date')->nullable();
            $table->date('eol_date')->nullable();
            $table->date('last_time_buy_date')->nullable();
            $table->text('eol_reason')->nullable();
            $table->text('manufacturer_notes')->nullable();
            $table->timestamps();
            
            $table->index(['lifecycle_stage', 'eol_date']);
        });

        // Alternative Components
        Schema::create('component_alternatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('original_component_id')->constrained('components')->onDelete('cascade');
            $table->foreignId('alternative_component_id')->constrained('components')->onDelete('cascade');
            $table->enum('alternative_type', [
                'direct_replacement', // Drop-in replacement
                'functional_equivalent', // Same function, may need design changes
                'pin_compatible', // Same pinout
                'form_factor_compatible' // Same package
            ]);
            $table->decimal('compatibility_score', 3, 2)->default(0.00); // 0.00 to 1.00
            $table->text('compatibility_notes')->nullable();
            $table->json('differences')->nullable(); // JSON delle differenze tecniche
            $table->boolean('is_recommended')->default(false);
            $table->timestamps();
            
            $table->unique(['original_component_id', 'alternative_component_id']);
            $table->index(['alternative_type', 'compatibility_score']);
        });

        // Obsolescence Alerts
        Schema::create('obsolescence_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->onDelete('cascade');
            $table->enum('alert_type', [
                'eol_warning', // 6+ months before EOL
                'eol_imminent', // < 6 months before EOL
                'last_time_buy', // Last chance to order
                'obsolete' // Component is obsolete
            ]);
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->string('title');
            $table->text('message');
            $table->json('affected_projects')->nullable(); // Project IDs that use this component
            $table->timestamp('alert_date');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users');
            $table->boolean('is_resolved')->default(false);
            $table->timestamps();
            
            $table->index(['alert_type', 'severity', 'is_resolved']);
            $table->index('alert_date');
        });

        // Component Certifications
        Schema::create('component_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->onDelete('cascade');
            $table->string('certification_type'); // CE, FCC, RoHS, REACH, etc.
            $table->string('certificate_number')->nullable();
            $table->string('issuing_authority')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['valid', 'expired', 'pending', 'revoked'])->default('valid');
            $table->text('scope')->nullable(); // What the certification covers
            $table->json('test_standards')->nullable(); // Array of test standards
            $table->string('certificate_file_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['certification_type', 'status']);
            $table->index('expiry_date');
        });

        // Supplier Risk Assessment
        Schema::create('supplier_risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->json('risk_factors')->nullable(); // Geographic, financial, capacity, etc.
            $table->decimal('financial_score', 3, 2)->nullable(); // 0.00 to 1.00
            $table->decimal('delivery_score', 3, 2)->nullable();
            $table->decimal('quality_score', 3, 2)->nullable();
            $table->decimal('geographic_diversification', 3, 2)->nullable();
            $table->text('assessment_notes')->nullable();
            $table->date('assessment_date');
            $table->date('next_review_date')->nullable();
            $table->foreignId('assessed_by')->constrained('users');
            $table->timestamps();
            
            $table->index(['risk_level', 'assessment_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_risk_assessments');
        Schema::dropIfExists('component_certifications');
        Schema::dropIfExists('obsolescence_alerts');
        Schema::dropIfExists('component_alternatives');
        Schema::dropIfExists('component_lifecycle_statuses');
    }
};