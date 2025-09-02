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
            //
            $table->string('data_owner_name')->nullable();
            $table->string('data_owner_surname')->nullable();
            $table->string('data_owner_email')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            //
            $table->dropColumn('data_owner_name');
            $table->dropColumn('data_owner_surname');
            $table->dropColumn('data_owner_email');
        });
    }
};
