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
            $table->boolean('is_ticket_deadline')->default(false)->after('reminder_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_reminders', function (Blueprint $table) {
            $table->dropColumn('is_ticket_deadline');
        });
    }
};
