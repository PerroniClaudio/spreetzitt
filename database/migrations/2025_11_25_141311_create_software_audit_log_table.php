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
        Schema::create('software_audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('modified_by')->nullable();
            $table->unsignedBigInteger('software_id')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->string('log_subject'); // software, software_user, software_company
            $table->string('log_type'); // create, delete, update, permanent-delete
            $table->timestamps();

            $table->foreign('modified_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('software_id')->references('id')->on('software')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('software_audit_log');
    }
};
