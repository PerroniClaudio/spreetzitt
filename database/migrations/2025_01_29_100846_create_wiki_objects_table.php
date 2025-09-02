<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('wiki_objects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('uploaded_name');
            $table->string('type'); // file o cartella
            $table->string('mime_type');
            $table->string('path');
            $table->string('file_size')->nullable();
            $table->boolean('is_public')->default(false); // se visibile dagli utenti o meno
            $table->foreignId('company_id')->nullable()->constrained();
            $table->foreignId('uploaded_by')->constrained('users');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('wiki_objects');
    }
};
