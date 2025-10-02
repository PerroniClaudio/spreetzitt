<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_pro_forma_bills', function (Blueprint $table) {
            $table->id();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->json('optional_parameters')->nullable();
            $table->boolean('is_generated')->default(false);
            $table->boolean('is_failed')->default(false);
            $table->text('error_message')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_pro_forma_bills');
    }
};