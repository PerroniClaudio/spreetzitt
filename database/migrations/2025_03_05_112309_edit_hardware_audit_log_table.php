<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hardware_audit_log', function (Blueprint $table) {
            $table->dropForeign(['modified_by']);
            $table->dropForeign(['hardware_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hardware_audit_log', function (Blueprint $table) {
            // Verifica che le colonne esistano prima di aggiungere le chiavi esterne
            if (Schema::hasColumn('hardware_audit_log', 'modified_by') && Schema::hasColumn('hardware_audit_log', 'hardware_id')) {
                // Rimuovi i valori non validi prima di aggiungere il vincolo
                DB::statement('UPDATE hardware_audit_log SET modified_by = NULL WHERE modified_by NOT IN (SELECT id FROM users)');
                DB::statement('UPDATE hardware_audit_log SET hardware_id = NULL WHERE hardware_id NOT IN (SELECT id FROM hardware)');
                
                $table->foreign('modified_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('hardware_id')->references('id')->on('hardware')->onDelete('set null');
            }
        });
    }
};
