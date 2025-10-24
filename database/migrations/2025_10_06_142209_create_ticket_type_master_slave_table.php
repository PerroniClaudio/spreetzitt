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
        Schema::create('ticket_type_master_slave', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('master_type_id');
            $table->unsignedBigInteger('slave_type_id');
            $table->boolean('is_required')->default(false)->comment('Se true, il ticket slave va aperto obbligatoriamente insieme al master');
            $table->foreign('master_type_id')->references('id')->on('ticket_types')->onDelete('cascade');
            $table->foreign('slave_type_id')->references('id')->on('ticket_types')->onDelete('cascade');
            $table->unique(['master_type_id', 'slave_type_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_type_master_slave');
    }
};