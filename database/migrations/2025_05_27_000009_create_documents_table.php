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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type'); // datasheet, manual, invoice, ddt, etc.
            $table->string('file_path')->nullable();
            $table->string('syncthing_path')->nullable(); // Path in Syncthing
            $table->string('mime_type')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('documentable_type')->nullable(); // Polymorphic relation
            $table->unsignedBigInteger('documentable_id')->nullable();
            $table->json('metadata')->nullable(); // Additional metadata
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();
            
            $table->index(['documentable_type', 'documentable_id']);
            $table->index('type');
            $table->fullText('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};