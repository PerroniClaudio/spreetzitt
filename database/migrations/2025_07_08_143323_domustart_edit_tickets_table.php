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
        //
        Schema::table('tickets', function (Blueprint $table) {
            // Aggiungi i nuovi campi alla tabella tickets
            $table->boolean('is_visible_all_users')->default(false)->after('is_billable');
            $table->boolean('is_visible_admin')->default(false)->after('is_visible_all_users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('tickets', function (Blueprint $table) {
            // Rimuovi i campi aggiunti
            $table->dropColumn(['is_visible_all_users', 'is_visible_admin']);
        });
    }
};
