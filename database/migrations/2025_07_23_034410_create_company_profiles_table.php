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
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            
            // Dati amministratore/proprietario
            $table->string('owner_name')->default('Alessandro Cursoli');
            $table->string('owner_title')->default('Amministratore Unico');
            
            // Dati aziendali
            $table->string('company_name')->default('Supernova Industries S.R.L.');
            $table->string('vat_number')->default('08959350722');
            $table->string('tax_code')->default('08959350722');
            $table->string('sdi_code')->default('M5UXCR1');
            
            // Indirizzo sede legale
            $table->string('legal_address')->default('Viale Papa Giovanni XXIII 193');
            $table->string('legal_city')->default('Bari');
            $table->string('legal_postal_code')->default('70124');
            $table->string('legal_province')->default('BA');
            $table->string('legal_country')->default('Italia');
            
            // Contatti
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('pec')->nullable();
            
            // Integrazione Claude AI
            $table->text('claude_api_key')->nullable();
            $table->string('claude_model')->default('claude-3-sonnet-20240229');
            $table->boolean('claude_enabled')->default(false);
            
            // Configurazioni email
            $table->string('smtp_host')->nullable();
            $table->integer('smtp_port')->default(587);
            $table->string('smtp_username')->nullable();
            $table->string('smtp_password')->nullable();
            $table->string('smtp_encryption')->default('tls');
            $table->string('mail_from_address')->nullable();
            $table->string('mail_from_name')->nullable();
            
            // Logo e immagini
            $table->string('logo_path')->nullable();
            $table->string('letterhead_path')->nullable();
            
            // Note aggiuntive
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
