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
        Schema::table('components', function (Blueprint $table) {
            // Valori elettrici
            $table->string('value')->nullable()->index(); // 10uF, 100R, etc.
            $table->string('tolerance')->nullable()->index(); // ±5%, ±10%, etc.
            $table->string('voltage_rating')->nullable()->index(); // 25V, 50V, etc.
            $table->string('current_rating')->nullable()->index(); // 1A, 10A, etc.
            $table->string('power_rating')->nullable()->index(); // 1/4W, 1W, etc.
            
            // Caratteristiche fisiche
            $table->string('package_type')->nullable()->index(); // 0603, 0805, SOIC-8, etc.
            $table->string('mounting_type')->nullable()->index(); // SMD, THT, etc.
            $table->string('case_style')->nullable()->index(); // TO-220, SOT-23, etc.
            
            // Specifiche per condensatori
            $table->string('dielectric')->nullable()->index(); // X7R, C0G, Y5V, etc.
            $table->string('temperature_coefficient')->nullable()->index();
            
            // Specifiche generali
            $table->string('operating_temperature')->nullable(); // -40°C ~ +85°C
            $table->jsonb('technical_attributes')->nullable(); // Altri attributi tecnici
            
            // Indici per ricerca veloce
            $table->index(['category_id', 'value']);
            $table->index(['category_id', 'package_type']);
            $table->index(['category_id', 'mounting_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->dropColumn([
                'value',
                'tolerance', 
                'voltage_rating',
                'current_rating',
                'power_rating',
                'package_type',
                'mounting_type',
                'case_style',
                'dielectric',
                'temperature_coefficient',
                'operating_temperature',
                'technical_attributes'
            ]);
        });
    }
};
