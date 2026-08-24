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
        if (! Schema::hasIndex('invoices', 'invoices_number_unique')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('invoices', 'invoices_number_unique')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique('number');
        });
    }
};
