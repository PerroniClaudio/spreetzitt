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
        Schema::create('ticket_stats', function (Blueprint $table) {
            $table->id();
            $table->integer('incident_open');
            $table->integer('incident_in_progress');
            $table->integer('incident_waiting');
            $table->integer('incident_out_of_sla');
            $table->integer('request_open');
            $table->integer('request_in_progress');
            $table->integer('request_waiting');
            $table->integer('request_out_of_sla');
            $table->longText('compnanies_opened_tickets');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_stats');
    }
};
