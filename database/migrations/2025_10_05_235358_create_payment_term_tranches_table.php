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
        Schema::create('payment_term_tranches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_term_id')->constrained()->cascadeOnDelete();

            // Tranche details
            $table->string('name'); // 'Acconto', 'Saldo', 'Prima rata', etc.
            $table->decimal('percentage', 5, 2); // 30.00, 70.00, etc.
            $table->integer('days_offset')->default(0); // Days from project start/invoice date
            $table->string('trigger_event')->default('contract'); // contract, delivery, completion, custom
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['payment_term_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_term_tranches');
    }
};
