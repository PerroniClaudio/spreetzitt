<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('old_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('old_ticket_id')->index();
            $table->string('business_name');
            $table->string('opened_by');
            $table->string('ticket_type'); // obj nella vecchia
            $table->date('opened_at');
            $table->date('closed_at')->nullable();
            $table->longText('closing_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('old_tickets');
    }
};
