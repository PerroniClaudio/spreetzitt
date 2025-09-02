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
        Schema::dropIfExists('company_ticket_types');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('company_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('ticket_type_id');
            $table->timestamps();
            $table->integer('sla_taking_charge')->default(0);
            $table->integer('sla_resolving')->default(0);

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('ticket_type_id')->references('id')->on('ticket_types');
        });
    }
};
