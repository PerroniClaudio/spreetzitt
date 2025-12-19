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
        Schema::table('software_attachments', function (Blueprint $table) {
            $table->string('access_level', 20)
                ->default('superadmin')
                ->after('uploaded_by')
                ->comment('Livello minimo richiesto per visualizzare questo allegato');
            
            $table->string('uploaded_by_level', 20)
                  ->after('access_level')
                  ->comment('Livello di chi ha caricato il file (immutabile, definisce chi può modificare access_level)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('software_attachments', function (Blueprint $table) {
            $table->dropColumn(['access_level', 'uploaded_by_level']);
        });
    }
};
