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
        Schema::create('ticket_log_ticket', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_log_id'); // Riferimento al log
            $table->unsignedBigInteger('ticket_id'); // Riferimento al ticket coinvolto
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('ticket_log_id')->references('id')->on('tickets_logs')->onDelete('cascade');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
            
            // Indici per performance
            $table->index('ticket_log_id');
            $table->index('ticket_id');
            
            // Previeni duplicati
            $table->unique(['ticket_log_id', 'ticket_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_log_ticket');
    }
};
