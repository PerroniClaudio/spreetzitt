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

        Schema::table('company_ticket_types', function (Blueprint $table) {
            $table->integer('sla_taking_charge')->default(0);
            $table->integer('sla_resolving')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //

        Schema::table('company_ticket_types', function (Blueprint $table) {
            $table->dropColumn('sla_taking_charge');
            $table->dropColumn('sla_resolving');
        });
    }
};
