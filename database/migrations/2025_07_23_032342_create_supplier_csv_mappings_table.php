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
        Schema::create('supplier_csv_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->string('field_name'); // Campo del sistema (es. 'manufacturer_part_number', 'unit_price')
            $table->string('csv_column_name'); // Nome colonna nel CSV del fornitore
            $table->integer('column_index')->nullable(); // Indice colonna nel CSV (opzionale)
            $table->string('default_value')->nullable(); // Valore di default se colonna non esiste
            $table->boolean('is_required')->default(false); // Se il campo è obbligatorio
            $table->enum('data_type', ['string', 'decimal', 'integer', 'date', 'boolean'])->default('string');
            $table->json('transformation_rules')->nullable(); // Regole di trasformazione
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indici per performance
            $table->index(['supplier_id', 'field_name']);
            $table->index(['supplier_id', 'is_active']);
            
            // Constraint per evitare duplicati
            $table->unique(['supplier_id', 'field_name'], 'unique_supplier_field');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_csv_mappings');
    }
};
