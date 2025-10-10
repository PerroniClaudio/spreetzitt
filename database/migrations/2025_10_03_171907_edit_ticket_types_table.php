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
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->boolean('is_scheduling')->default(false)->description('Indica se il tipo di ticket è attività programmata, quindi per raggruppare altri ticket es. per giornata on site')->after('is_master');
            $table->boolean('is_grouping')->default(false)->description('Indica se il tipo di ticket è per il raggruppamento di altri ticket. es. un problema al quale risalgono più ticket.')->after('is_scheduling');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('is_scheduling');
            $table->dropColumn('is_grouping');
        });
    }
};
