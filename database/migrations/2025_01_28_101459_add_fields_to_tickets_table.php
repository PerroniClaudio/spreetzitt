<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('tickets', function (Blueprint $table) {
            //
            $table->boolean('is_form_correct')->default(true);
            $table->boolean('was_user_self_sufficient')->default(false);
            $table->boolean('is_user_error_problem')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('tickets', function (Blueprint $table) {
            //
            $table->dropColumn('is_form_correct');
            $table->dropColumn('was_user_self_sufficient');
            $table->dropColumn('is_user_error_problem');
        });
    }
};
