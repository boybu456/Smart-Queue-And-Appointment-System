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
        Schema::create('queue_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('users')->cascadeOnDelete();

            $table->string('token', 10); // store token e.g. "A012"
            $table->unsignedInteger('position');
            $table->enum('status', ['waiting', 'called', 'serving', 'done', 'skipped'])->default('waiting');
            $table->timestamp('joined_at');
            $table->timestamp('served_at')->nullable();
            $table->timestamps();
            // Speed up queries that filter by service + status
            $table->index(['service_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_entries');
    }
};
