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
            $table->unsignedBigInteger('scheduling_id')->nullable()->after('master_id')->comment('Riferimento ad un ticket di tipo attività programmata.');
            $table->unsignedBigInteger('grouping_id')->nullable()->after('scheduling_id')->comment('Riferimento ad un ticket di tipo raggruppamento.');

            $table->foreign('scheduling_id')->references('id')->on('tickets')->nullOnDelete();
            $table->foreign('grouping_id')->references('id')->on('tickets')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['scheduling_id']);
            $table->dropForeign(['grouping_id']);
            $table->dropColumn('scheduling_id');
            $table->dropColumn('grouping_id');
        });
    }
};
