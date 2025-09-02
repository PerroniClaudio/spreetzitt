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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('uploaded_name');
            $table->string('type'); // 'file' or 'folder'
            $table->string('mime_type')->nullable();
            $table->string('path');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('uploaded_by');
            $table->unsignedInteger('file_size')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes for better performance
            $table->index('company_id');
            $table->index('uploaded_by');
            $table->index('type');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
