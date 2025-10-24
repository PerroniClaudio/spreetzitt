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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Chiave esterna per l'utente
            $table->unsignedBigInteger('company_id'); // Chiave esterna per l'azienda
            $table->string('status');
            $table->text('description');
            $table->string('file')->nullable();
            $table->string('duration'); // Durata del ticket in minuti
            $table->unsignedBigInteger('admin_user_id')->nullable(); // Chiave esterna per l'utente admin
            $table->unsignedBigInteger('group_id')->nullable(); // Chiave esterna per il gruppo
            $table->timestamp('due_date')->nullable();
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade'); // Esegui l'eliminazione a cascata quando l'utente viene eliminato

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade'); // Esegui l'eliminazione a cascata quando l'azienda viene eliminata

            $table->foreign('admin_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null'); // Imposta a null quando l'utente admin viene eliminato

            $table->foreign('group_id')
                ->references('id')
                ->on('groups')
                ->onDelete('set null'); // Imposta a null quando il gruppo viene eliminato
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
