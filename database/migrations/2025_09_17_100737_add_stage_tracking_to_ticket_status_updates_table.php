<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Aggiunge campi per tracciare i cambiamenti di stage nei ticket status updates.
     * Questi campi permettono di identificare precisamente i cambiamenti di stato
     * anche quando i nomi degli stage sono personalizzabili.
     */
    public function up(): void
    {
        Schema::table('ticket_status_updates', function (Blueprint $table) {
            // Campi per tracciare i cambiamenti di stage
            $table->foreignId('old_stage_id')
                  ->nullable()
                  ->after('content') // Assumendo che esista un campo 'content'
                  ->constrained('ticket_stages')
                  ->onDelete('set null')
                  ->comment('Stage precedente del ticket (NULL se non è un cambio di stato)');
                  
            $table->foreignId('new_stage_id')
                  ->nullable()
                  ->after('old_stage_id')
                  ->constrained('ticket_stages')
                  ->onDelete('set null')
                  ->comment('Nuovo stage del ticket');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_status_updates', function (Blueprint $table) {
            
            // Rimuovi foreign key constraints e colonne
            $table->dropForeign(['old_stage_id']);
            $table->dropForeign(['new_stage_id']);
            $table->dropColumn(['old_stage_id', 'new_stage_id']);
        });
    }
};
