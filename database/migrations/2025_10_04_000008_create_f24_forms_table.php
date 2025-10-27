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
        Schema::create('f24_forms', function (Blueprint $table) {
            $table->id();

            $table->string('form_number', 50)->unique();

            // Tipo
            $table->string('type', 50); // imu, tasi, iva, inps, inail, irpef, other

            // Periodo di riferimento
            $table->integer('reference_month')->nullable();
            $table->integer('reference_year');

            // Importi
            $table->decimal('total_amount', 12, 2);

            // Date
            $table->date('payment_date');
            $table->date('due_date')->nullable();

            // Link a cliente (se F24 per cliente specifico)
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // File
            $table->text('nextcloud_path')->nullable();

            // Note
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('reference_year');
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('f24_forms');
    }
};
