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
        Schema::create('staff_availability', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->cascadeOnDelete();

            // note 1-7 days ex: 0 = Sunday, 1 = Monday, ..., 6 = Saturday
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_availability');
    }
};
