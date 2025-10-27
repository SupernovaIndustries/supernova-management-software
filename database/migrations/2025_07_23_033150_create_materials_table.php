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
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Codice univoco materiale
            $table->string('name'); // Nome materiale
            $table->text('description')->nullable(); // Descrizione
            $table->enum('category', [
                'filament',         // Filamenti stampante 3D
                'resin',           // Resine
                'stationery',      // Cancelleria
                'consumable',      // Materiali di consumo
                'chemical',        // Prodotti chimici
                'packaging',       // Materiali imballaggio
                'other'            // Altri materiali
            ]); 
            $table->string('brand')->nullable(); // Marca/produttore
            $table->string('model')->nullable(); // Modello
            $table->string('color')->nullable(); // Colore (per filamenti/resine)
            $table->decimal('diameter', 5, 2)->nullable(); // Diametro (per filamenti)
            $table->string('material_type')->nullable(); // Tipo materiale (PLA, ABS, PETG, etc.)
            $table->decimal('weight_kg', 8, 3)->nullable(); // Peso in kg
            $table->decimal('length_m', 10, 2)->nullable(); // Lunghezza in metri
            $table->decimal('unit_price', 10, 4)->nullable(); // Prezzo unitario
            $table->string('currency', 3)->default('EUR'); // Valuta
            $table->integer('stock_quantity')->default(0); // Quantità in stock
            $table->integer('min_stock_level')->default(0); // Livello minimo stock
            $table->string('unit_of_measure')->default('pcs'); // Unità di misura (pcs, kg, m, l)
            $table->string('storage_location')->nullable(); // Posizione magazzino
            $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');
            $table->string('supplier')->nullable(); // Fornitore
            $table->string('supplier_code')->nullable(); // Codice fornitore
            $table->date('purchase_date')->nullable(); // Data acquisto
            $table->date('expiry_date')->nullable(); // Data scadenza
            $table->decimal('temperature_storage_min', 5, 2)->nullable(); // Temperatura stoccaggio min
            $table->decimal('temperature_storage_max', 5, 2)->nullable(); // Temperatura stoccaggio max
            $table->text('notes')->nullable(); // Note aggiuntive
            $table->json('specifications')->nullable(); // Specifiche tecniche
            $table->string('image_path')->nullable(); // Percorso immagine
            $table->string('datasheet_path')->nullable(); // Percorso scheda tecnica
            $table->timestamps();

            // Indici per performance
            $table->index(['category', 'status']);
            $table->index(['brand', 'model']);
            $table->index('stock_quantity');
            $table->index('supplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
