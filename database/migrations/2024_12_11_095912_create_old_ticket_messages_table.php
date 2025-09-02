<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('old_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->string('old_ticket_id');
            $table->string('sender');
            $table->longText('message');
            $table->date('sent_at');
            $table->boolean('is_admin');
            $table->timestamps();

            $table->foreign('old_ticket_id')->references('old_ticket_id')->on('old_tickets');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('old_ticket_messages');
    }
};
