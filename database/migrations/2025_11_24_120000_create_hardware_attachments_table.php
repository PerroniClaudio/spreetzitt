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
        Schema::create('hardware_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hardware_id');
            
            // File storage info
            $table->string('file_path'); // Path su GCS
            $table->string('original_filename'); // Nome originale del file
            $table->string('display_name')->nullable(); // Nome personalizzato (es: "Fattura riparazione")
            $table->string('file_extension', 10); // .pdf, .jpg, ecc.
            $table->string('mime_type', 100); // application/pdf, image/jpeg
            $table->unsignedBigInteger('file_size')->nullable(); // bytes
            
            // Metadata
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->softDeletes(); // Soft delete
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('hardware_id')->references('id')->on('hardware')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
            
            // Index per query veloci
            $table->index('hardware_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hardware_attachments');
    }
};
