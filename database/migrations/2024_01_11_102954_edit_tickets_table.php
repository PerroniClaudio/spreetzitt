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
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->unsignedBigInteger('company_id')->nullable()->change();

            // Modifica chiavi esterne
            $table->dropForeign(['user_id']);
            $table->dropForeign(['company_id']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('no action');

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Modifica chiavi esterne
            $table->dropForeign(['user_id']);
            $table->dropForeign(['company_id']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade'); // Esegui l'eliminazione a cascata quando l'utente viene eliminato

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade'); // Esegui l'eliminazione a cascata quando l'azienda viene eliminata
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
        });
    }
};
