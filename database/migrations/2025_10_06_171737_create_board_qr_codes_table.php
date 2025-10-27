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
        Schema::create('board_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_assembly_log_id')
                ->constrained('board_assembly_logs')
                ->cascadeOnDelete();
            $table->integer('board_number')->comment('Sequential board number within the assembly batch (1, 2, 3...)');
            $table->string('qr_data')->comment('QR code content: PROJECT_CODE-BATCH_NUMBER-BOARD_NUMBER-DATE');
            $table->string('qr_file_path')->comment('Nextcloud path to the QR code PNG file');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('board_assembly_log_id');
            $table->unique(['board_assembly_log_id', 'board_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('board_qr_codes');
    }
};
