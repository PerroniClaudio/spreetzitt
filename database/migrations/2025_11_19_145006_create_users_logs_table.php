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
        Schema::create('users_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('modified_by')->nullable()->description('Chi ha fatto la modifica');
            $table->unsignedBigInteger('user_id')->nullable()->description('Chi ha subito la modifica');
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->string('log_subject'); //hardware, hardware_user, hardware_company
            $table->string('log_type'); //create, delete, update, permanent-delete

            // Foreign key constraint
            $table->foreign('modified_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            
            // Indici per performance
            $table->index(['log_type', 'created_at']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_logs');
    }
};
