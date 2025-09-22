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
        Schema::table('ticket_reminders', function (Blueprint $table) {
            // Gli ID di Microsoft Graph possono essere molto lunghi, cambiamo a VARCHAR(255)
            $table->string('event_uuid', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_reminders', function (Blueprint $table) {
            // Ripristina il tipo UUID originale
            $table->uuid('event_uuid')->change();
        });
    }
};
