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
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('manufacturer_part_number')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained();
            $table->string('manufacturer')->nullable();
            $table->string('package')->nullable(); // SMD package type
            $table->json('specifications')->nullable(); // Technical specs
            $table->string('datasheet_url')->nullable();
            $table->string('image_url')->nullable();
            $table->decimal('unit_price', 10, 4)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->integer('stock_quantity')->default(0);
            $table->integer('min_stock_level')->default(0);
            $table->integer('reorder_quantity')->default(0);
            $table->string('storage_location')->nullable();
            $table->string('status')->default('active'); // active, obsolete, discontinued
            $table->json('supplier_links')->nullable(); // Links to supplier pages
            $table->timestamps();
            
            $table->index(['sku', 'status']);
            $table->index('manufacturer_part_number');
            $table->index('stock_quantity');
            $table->fullText(['name', 'description']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('components');
    }
};