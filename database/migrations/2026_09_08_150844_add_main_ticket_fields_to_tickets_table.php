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
            $table->boolean('is_main')->default(false)->after('scheduling_id');
            $table->unsignedBigInteger('main_id')->nullable()->after('is_main');
            $table->foreign('main_id')->references('id')->on('tickets')->nullOnDelete();
            $table->index(['is_main', 'company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['main_id']);
            $table->dropIndex(['is_main', 'company_id']);
            $table->dropColumn(['is_main', 'main_id']);
        });
    }
};
