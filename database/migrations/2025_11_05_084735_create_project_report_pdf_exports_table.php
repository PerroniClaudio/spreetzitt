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
        Schema::create('project_report_pdf_exports', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('file_name');
            $table->string('file_path');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('optional_parameters');
            $table->boolean('is_generated')->default(false);
            $table->boolean('is_user_generated')->default(false);
            $table->boolean('is_failed')->default(false);
            $table->text('error_message')->nullable();
            $table->boolean('is_approved_billing')->default(false); // Per indicare se è stato approvato per l'utilizzo come "bolletta" mensile o quello che è
            $table->string('approved_billing_identification')->nullable();
            $table->unique('approved_billing_identification', 'proj_rep_billing_id_unique'); // Nome personalizzato per evitare il limite di 64 caratteri

            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies');
            
            $table->unsignedBigInteger('project_id')->nullable();
            $table->foreign('project_id')->references('id')->on('tickets');

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_report_pdf_exports');
    }
};
