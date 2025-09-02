<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('hardware', function (Blueprint $table) {
            $table->string('status')->default('new');
            $table->string('position')->default('support');
        });
    }

    public function down(): void {
        Schema::table('hardware', function (Blueprint $table) {
            $table->dropColumn(['status', 'position']);
        });
    }
};
