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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('section');
            $table->string('sheet');
            $table->string('parcel');
            $table->unsignedInteger('users_number')->nullable();
            $table->string('energy_class');
            $table->float('square_meters');
            $table->float('thousandths');
            $table->unsignedTinyInteger('activity_type'); // int al posto di enum
            $table->unsignedTinyInteger('in_use_by'); // int al posto di enum
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
