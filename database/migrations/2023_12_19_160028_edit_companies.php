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
        Schema::table('companies', function (Blueprint $table) {
            $table->integer('sla_take_low')->nullable();
            $table->integer('sla_take_medium')->nullable();
            $table->integer('sla_take_high')->nullable();
            $table->integer('sla_take_critical')->nullable();
            $table->integer('sla_solve_low')->nullable();
            $table->integer('sla_solve_medium')->nullable();
            $table->integer('sla_solve_high')->nullable();
            $table->integer('sla_solve_critical')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('sla_take_low');
            $table->dropColumn('sla_take_medium');
            $table->dropColumn('sla_take_high');
            $table->dropColumn('sla_take_critical');
            $table->dropColumn('sla_solve_low');
            $table->dropColumn('sla_solve_medium');
            $table->dropColumn('sla_solve_high');
            $table->dropColumn('sla_solve_critical');
        });
    }
};
