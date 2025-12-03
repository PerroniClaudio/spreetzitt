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
            $table->string('hardware_accessory_include')->nullable()->comment('Indica se includere l\'hardware, gli accessori o entrambi nel caso in cui il campo sia di tipo hardware');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('type_form_fields', function (Blueprint $table) {
            $table->dropColumn('hardware_accessory_include');
        });
    }
};
