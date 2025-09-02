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
        Schema::create('ticket_report_pdf_exports', function (Blueprint $table) {
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
            $table->string('error_message')->nullable();
            $table->boolean('is_approved_billing')->default(false); // Per indicare se è stato approvato per l'utilizzo come "bolletta" mensile o quello che è
            $table->string('approved_billing_identification')->nullable()->unique(); // Da usare come identificativo riportato nelle fatture dato che probabilmente non vuole usare l'id

            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_report_pdf_exports');
    }
};
