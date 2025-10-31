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
        Schema::table('tickets', function (Blueprint $table) {
            // Campi per i progetti
            $table->string('project_name')->nullable()->after('description');
            $table->date('project_start')->nullable()->after('project_name');
            $table->date('project_end')->nullable()->after('project_start');
            $table->integer('project_expected_duration')->nullable()->after('project_end')->comment('Durata prevista in minuti per il progetto');
            
            // Campo per collegare ticket a un progetto
            $table->unsignedBigInteger('project_id')->nullable()->after('scheduling_id');
            $table->foreign('project_id')->references('id')->on('tickets')->onDelete('set null');
            
            // Indice per performance
            $table->index('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['project_id']);
            $table->dropColumn(['project_name', 'project_start', 'project_end', 'project_expected_duration', 'project_id']);
        });
    }
};
