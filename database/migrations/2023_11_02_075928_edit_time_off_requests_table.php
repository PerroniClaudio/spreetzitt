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
        Schema::table('time_off_requests', function (Blueprint $table) {
            $table->dateTime('date_from')->change();
            $table->dateTime('date_to')->change();
            $table->string('batch_id')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_off_requests', function (Blueprint $table) {
            $table->date('date_from')->change();
            $table->date('date_to')->change();
        });
    }
};
