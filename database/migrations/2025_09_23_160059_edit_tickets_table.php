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
            $table->boolean('is_billed')->default(false)->after('is_billable');
            $table->string('bill_identification')->nullable()->after('is_billed')->description('Identificativo fattura, se il ticket è stato fatturato');
            $table->date('bill_date')->nullable()->after('bill_identification')->description('Data di fatturazione del ticket, se il ticket è stato fatturato');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('is_billed');
            $table->dropColumn('bill_identification');
            $table->dropColumn('bill_date');
        });
    }
};
