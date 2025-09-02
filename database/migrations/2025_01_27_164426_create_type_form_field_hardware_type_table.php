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
        Schema::create('type_form_field_hardware_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hardware_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('type_form_field_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('type_form_field_hardware_type');
    }
};
