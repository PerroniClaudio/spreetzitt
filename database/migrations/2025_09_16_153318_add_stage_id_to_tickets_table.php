<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * NOTA: Questa è una migration temporanea per la fase di transizione.
     * Dopo la migrazione dei dati, stage_id diventerà NOT NULL.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // TEMPORANEO: nullable per permettere migrazione dati esistenti
            $table->foreignId('stage_id')
                ->nullable()
                ->after('status')
                ->constrained('ticket_stages')
                ->onDelete('set null') // TEMPORANEO: safety net durante transizione
                ->comment('TEMP: Reference to ticket_stages. Will become NOT NULL after data migration.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Rimuovi la foreign key constraint e la colonna
            $table->dropForeign(['stage_id']);
            $table->dropColumn('stage_id');
        });
    }
};
