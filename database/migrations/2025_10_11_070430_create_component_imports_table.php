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
        Schema::create('component_imports', function (Blueprint $table) {
            $table->id();

            // User who performed the import
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Import details
            $table->string('job_id')->unique()->index();
            $table->string('supplier'); // mouser, digikey, farnell, etc.
            $table->string('original_filename');
            $table->string('file_type'); // excel, csv

            // Import statistics
            $table->integer('components_imported')->default(0);
            $table->integer('components_updated')->default(0);
            $table->integer('components_skipped')->default(0);
            $table->integer('components_failed')->default(0);
            $table->integer('movements_created')->default(0);

            // Invoice information (if provided)
            $table->string('invoice_number')->nullable();
            $table->string('invoice_path')->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('invoice_total', 12, 2)->nullable();
            $table->foreignId('destination_project_id')->nullable()->constrained('projects')->onDelete('set null');

            // Status and timing
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Additional metadata
            $table->json('field_mapping')->nullable(); // Store the field mapping used
            $table->json('import_details')->nullable(); // Store detailed import results
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes(); // Allow soft delete

            // Indexes
            $table->index('supplier');
            $table->index('status');
            $table->index('invoice_number');
            $table->index('created_at');
        });

        // Add foreign key to components table to track which import created them
        Schema::table('components', function (Blueprint $table) {
            $table->foreignId('import_id')->nullable()->after('id')->constrained('component_imports')->onDelete('set null');
        });

        // Add foreign key to inventory_movements table to track which import created them
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('import_id')->nullable()->after('id')->constrained('component_imports')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove foreign keys first
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['import_id']);
            $table->dropColumn('import_id');
        });

        Schema::table('components', function (Blueprint $table) {
            $table->dropForeign(['import_id']);
            $table->dropColumn('import_id');
        });

        Schema::dropIfExists('component_imports');
    }
};
