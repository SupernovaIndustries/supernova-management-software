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
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Codice inventario univoco
            $table->string('name'); // Nome attrezzatura
            $table->text('description')->nullable(); // Descrizione
            $table->enum('category', [
                'computer',        // Computer e laptop
                'soldering',       // Saldatori e stazioni saldanti
                'reflow',          // Forni reflow
                'cnc',             // Macchine CNC
                '3d_printer',      // Stampanti 3D
                'laser',           // Laser cutter/engraver
                'measurement',     // Strumenti di misura
                'power_supply',    // Alimentatori
                'oscilloscope',    // Oscilloscopi
                'multimeter',      // Multimetri
                'generator',       // Generatori di segnale
                'microscope',      // Microscopi
                'camera',          // Fotocamere e videocamere
                'tool',            // Utensili vari
                'furniture',       // Mobili e scaffalature
                'other'            // Altre attrezzature
            ]);
            $table->string('brand'); // Marca
            $table->string('model'); // Modello
            $table->string('serial_number')->nullable(); // Numero seriale
            $table->decimal('purchase_price', 10, 2)->nullable(); // Prezzo acquisto
            $table->string('currency', 3)->default('EUR'); // Valuta
            $table->date('purchase_date')->nullable(); // Data acquisto
            $table->string('supplier')->nullable(); // Fornitore
            $table->string('invoice_reference')->nullable(); // Riferimento fattura
            $table->enum('status', [
                'active',          // In uso
                'maintenance',     // In manutenzione
                'broken',          // Guasto
                'retired',         // Dismesso
                'sold'             // Venduto
            ])->default('active');
            $table->string('location')->nullable(); // Posizione fisica
            $table->string('responsible_user')->nullable(); // Responsabile/assegnatario
            $table->date('warranty_expiry')->nullable(); // Scadenza garanzia
            $table->date('last_maintenance')->nullable(); // Ultima manutenzione
            $table->date('next_maintenance')->nullable(); // Prossima manutenzione
            $table->integer('maintenance_interval_months')->nullable(); // Intervallo manutenzione (mesi)
            $table->text('specifications')->nullable(); // Specifiche tecniche
            $table->json('technical_specs')->nullable(); // Specifiche dettagliate JSON
            $table->text('notes')->nullable(); // Note
            $table->string('image_path')->nullable(); // Foto attrezzatura
            $table->string('manual_path')->nullable(); // Manuale utente
            $table->string('qr_code')->nullable(); // Codice QR per identificazione
            $table->boolean('calibration_required')->default(false); // Richiede calibrazione
            $table->date('last_calibration')->nullable(); // Ultima calibrazione
            $table->date('next_calibration')->nullable(); // Prossima calibrazione
            $table->integer('calibration_interval_months')->nullable(); // Intervallo calibrazione
            $table->decimal('depreciation_rate', 5, 2)->nullable(); // Tasso ammortamento annuale
            $table->decimal('current_value', 10, 2)->nullable(); // Valore attuale stimato
            $table->timestamps();

            // Indici per performance
            $table->index(['category', 'status']);
            $table->index(['brand', 'model']);
            $table->index('location');
            $table->index('responsible_user');
            $table->index(['warranty_expiry', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
