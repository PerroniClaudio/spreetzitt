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
        // Rivediamo l'HardwareAuditLog
        // id, modified_by, hardware_id, user_id, old_data (json con contenuto in base alla modifica), new_data (json con contenuto in base alla modifica), edit_sujbect (hardware, hardware_user, hardware_company), edit_type (create, delete, update), created_at, updated_at
        // 
        Schema::create('hardware_audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('modified_by')->nullable();
            $table->unsignedBigInteger('hardware_id')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->string('log_subject'); //hardware, hardware_user, hardware_company
            $table->string('log_type'); //create, delete, update, permanent-delete
            $table->timestamps();

            $table->foreign('modified_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('hardware_id')->references('id')->on('hardware')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hardware_audit_log');
    }
};
