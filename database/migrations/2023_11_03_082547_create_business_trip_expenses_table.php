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
        Schema::create('business_trip_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_trip_id');
            $table->unsignedBigInteger('company_id');
            $table->integer('payment_type')->default(0);
            $table->integer('expense_type')->default(0);
            $table->decimal('amount', 8, 2)->unsigned();
            $table->dateTime('date');
            $table->string('address');
            $table->string('city');
            $table->string('province');
            $table->string('zip_code');
            $table->string('latitude');
            $table->string('longitude');

            $table->timestamps();

            $table->foreign('business_trip_id')->references('id')->on('business_trips');
            $table->foreign('company_id')->references('id')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_trip_expenses');
    }
};
