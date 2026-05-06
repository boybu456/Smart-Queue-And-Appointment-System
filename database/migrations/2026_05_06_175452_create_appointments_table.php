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
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // foreign keys — link to other tables
            $table->foreignUuid('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->cascadeOnDelete();
            // set Scheduke And Status
            $table->dateTime('scheduled_at');
            $table->enum('status', ['pending', 'confirmed', 'no_show', 'done', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
