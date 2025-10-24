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
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('assigned')->default(false)->after('group_id');

            $table->foreignId('last_assignment_id')
                ->nullable()
                ->after('assigned')
                ->constrained('ticket_assignment_history_records')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'last_assignment_id')) {
                $table->dropForeign(['last_assignment_id']);
                $table->dropColumn('last_assignment_id');
            }

            if (Schema::hasColumn('tickets', 'assigned')) {
                $table->dropColumn('assigned');
            }
        });
    }
};
