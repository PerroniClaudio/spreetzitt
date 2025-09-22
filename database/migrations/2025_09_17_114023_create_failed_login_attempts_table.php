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
        Schema::create('failed_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('attempt_type')->default('invalid_credentials'); // invalid_credentials, non_existent_user, unverified_user, disabled_user, invalid_otp, expired_otp
            $table->text('additional_data')->nullable(); // JSON per dati aggiuntivi
            $table->timestamps();

            $table->index(['email', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_login_attempts');
    }
};
