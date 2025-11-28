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
        Schema::table('software_user', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('software_user', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['responsible_user_id']);
            $table->dropColumn(['created_by', 'responsible_user_id']);
        });
    }
};
