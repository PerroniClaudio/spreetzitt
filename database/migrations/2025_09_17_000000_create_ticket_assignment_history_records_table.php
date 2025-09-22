<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_assignment_history_records', function (Blueprint $table) {
            $table->id();

            // Foreign key to tickets: if ticket is deleted, remove the history record as well
            $table->foreignId('ticket_id')
                ->constrained('tickets')
                ->cascadeOnDelete();

            // Admin user who handled the assignment. Keep the record if user is deleted (set null)
            $table->foreignId('admin_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Group handling the assignment. Keep the record if group is deleted (set null)
            $table->foreignId('group_id')
                ->nullable()
                ->constrained('groups')
                ->nullOnDelete();

            // Optional message for the assignment event
            $table->text('message')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_assignment_history_records');
    }
};
