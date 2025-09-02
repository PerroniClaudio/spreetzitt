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

        Schema::table('attendances', function(Blueprint $table) {

            $table->unsignedBigInteger('attendance_type_id');

            $table->foreign('attendance_type_id')
                ->references('id')
                ->on('attendance_types');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
