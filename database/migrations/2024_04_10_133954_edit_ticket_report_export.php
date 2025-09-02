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
        Schema::table('ticket_report_exports', function (Blueprint $table) {
            $table->boolean('is_user_generated')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('ticket_report_exports', function (Blueprint $table) {
            $table->dropColumn('is_user_generated');
        });
    }
};
