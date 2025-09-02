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
        Schema::create('hardware_company_audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // assegna azienda, modifica azienda, rimuove azienda
            $table->unsignedBigInteger('modified_by')->nullable(); //chi ha effettuato la modifica (può essere l'admin che lo assegna all'azienda ecc. o il company admin che lo assegna all'utente ecc.)
            $table->unsignedBigInteger('hardware_id')->nullable();
            $table->unsignedBigInteger('old_company_id')->nullable();
            $table->unsignedBigInteger('new_company_id')->nullable();
            $table->timestamps();

            $table->foreign('modified_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('hardware_id')->references('id')->on('hardware')->onDelete('set null');
            $table->foreign('old_company_id')->references('id')->on('companies')->onDelete('set null');
            $table->foreign('new_company_id')->references('id')->on('companies')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hardware_company_audit_log');
    }
};
