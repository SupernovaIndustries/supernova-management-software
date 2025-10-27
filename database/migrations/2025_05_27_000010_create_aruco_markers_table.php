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
        Schema::create('aruco_markers', function (Blueprint $table) {
            $table->id();
            $table->integer('marker_id')->unique(); // ArUco marker ID
            $table->string('type'); // component, box, shelf, location
            $table->string('trackable_type')->nullable(); // Polymorphic relation
            $table->unsignedBigInteger('trackable_id')->nullable();
            $table->string('location')->nullable();
            $table->json('metadata')->nullable(); // Additional tracking data
            $table->timestamp('last_scanned_at')->nullable();
            $table->foreignId('last_scanned_by')->nullable()->constrained('users');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['trackable_type', 'trackable_id']);
            $table->index('marker_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aruco_markers');
    }
};