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
        Schema::table('board_assembly_logs', function (Blueprint $table) {
            // DDT Basic Info
            $table->string('ddt_number')->nullable()->after('notes')->index();
            $table->date('ddt_date')->nullable()->after('ddt_number');

            // Transport Type (chi trasporta)
            $table->enum('ddt_transport_type', ['cedente', 'cessionario'])
                ->default('cedente')
                ->after('ddt_date')
                ->comment('cedente = trasporto a cura del mittente, cessionario = trasporto a cura del destinatario');

            // Delivery Address (can differ from customer address)
            $table->json('ddt_delivery_address')->nullable()->after('ddt_transport_type');

            // Transport Reason (causale del trasporto)
            $table->text('ddt_reason')->nullable()->after('ddt_delivery_address');

            // Payment Condition
            $table->enum('ddt_payment_condition', ['in_conto', 'in_saldo'])
                ->nullable()
                ->after('ddt_reason')
                ->comment('in_conto = acconto, in_saldo = saldo finale');

            // Package Information
            $table->integer('ddt_packages_count')->default(1)->after('ddt_payment_condition');
            $table->decimal('ddt_weight_kg', 8, 2)->nullable()->after('ddt_packages_count');
            $table->string('ddt_appearance')->default('scatola')->after('ddt_weight_kg');

            // Goods Description (AI-generated)
            $table->text('ddt_goods_description')->nullable()->after('ddt_appearance');

            // PDF Paths
            $table->string('ddt_pdf_path')->nullable()->after('ddt_goods_description');
            $table->string('ddt_signed_pdf_path')->nullable()->after('ddt_pdf_path');

            // Signatures (base64 or JSON data for signature pad)
            $table->text('ddt_conductor_signature')->nullable()->after('ddt_signed_pdf_path');
            $table->text('ddt_recipient_signature')->nullable()->after('ddt_conductor_signature');

            // Metadata
            $table->timestamp('ddt_generated_at')->nullable()->after('ddt_recipient_signature');
            $table->timestamp('ddt_signed_at')->nullable()->after('ddt_generated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('board_assembly_logs', function (Blueprint $table) {
            $table->dropColumn([
                'ddt_number',
                'ddt_date',
                'ddt_transport_type',
                'ddt_delivery_address',
                'ddt_reason',
                'ddt_payment_condition',
                'ddt_packages_count',
                'ddt_weight_kg',
                'ddt_appearance',
                'ddt_goods_description',
                'ddt_pdf_path',
                'ddt_signed_pdf_path',
                'ddt_conductor_signature',
                'ddt_recipient_signature',
                'ddt_generated_at',
                'ddt_signed_at',
            ]);
        });
    }
};
