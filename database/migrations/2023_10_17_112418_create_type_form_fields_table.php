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
        Schema::create('type_form_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_type_id');
            $table->string('field_name');
            $table->string('field_type');
            $table->string('field_label');
            $table->boolean('required');
            $table->string('description')->nullable();
            $table->string('placeholder')->nullable();
            $table->string('default_value')->nullable();
            $table->string('options')->nullable();
            $table->string('validation')->nullable();
            $table->string('validation_message')->nullable();
            $table->string('help_text')->nullable();
            $table->integer('order')->default(0);

            $table->timestamps();

            $table->foreign('ticket_type_id')
                ->references('id')
                ->on('ticket_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('type_form_fields');
    }
};
