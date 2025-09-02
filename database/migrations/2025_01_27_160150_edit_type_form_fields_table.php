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
        Schema::table('type_form_fields', function (Blueprint $table) {
            $table->integer('hardware_limit')->nullable();
            $table->boolean('include_no_type_hardware')->default(false); //Includere hardware senza un tipo associato
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('type_form_fields', function (Blueprint $table) {
            $table->dropColumn('hardware_limit');
            $table->dropColumn('include_no_type_hardware');
        });
    }
};
