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
        if (Schema::hasColumn('tickets', 'is_user_error') || Schema::hasColumn('tickets', 'is_user_error_problem')) {
            Schema::table('tickets', function (Blueprint $table) {
                if (Schema::hasColumn('tickets', 'is_user_error')) {
                    $table->dropColumn('is_user_error');
                }

                if (Schema::hasColumn('tickets', 'is_user_error_problem')) {
                    $table->dropColumn('is_user_error_problem');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'is_user_error')) {
                $table->boolean('is_user_error')->default(false)->after('wait_end');
            }

            if (! Schema::hasColumn('tickets', 'is_user_error_problem')) {
                $table->boolean('is_user_error_problem')->default(false)->after('was_user_self_sufficient');
            }
        });
    }
};
