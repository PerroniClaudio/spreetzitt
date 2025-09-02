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
        Schema::create('hardware_user_audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('type'); //tipo di modifica (assegnazione: created, rimozione: deleted, aggiornamento: updated) aggiornamento non succede, quindi bastano le due voci hardware id e user id
            $table->unsignedBigInteger('modified_by')->nullable(); //chi ha effettuato la modifica (può essere l'admin che lo assegna all'azienda ecc. o il company admin che lo assegna all'utente ecc.)
            $table->unsignedBigInteger('hardware_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('modified_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('hardware_id')->references('id')->on('hardware')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hardware_user_audit_log');
    }
};
