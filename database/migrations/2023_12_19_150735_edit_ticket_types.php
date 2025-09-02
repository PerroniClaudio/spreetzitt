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
            $table->string('default_priority')->nullable(false)->default('low');
            $table->integer('default_sla_solve')->nullable(false);
            $table->integer('default_sla_take')->nullable(false);
            $table->unsignedBigInteger('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('default_priority');
            $table->dropColumn('default_sla_solve');
            $table->dropColumn('default_sla_take');
            $table->dropColumn('company_id');
        });
    }
};
