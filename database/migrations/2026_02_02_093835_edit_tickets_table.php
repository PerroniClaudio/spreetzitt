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
            $table->foreignId('contract_id')
                ->nullable()
                ->constrained('contracts')
                ->nullOnDelete();
            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained('invoices')
                ->nullOnDelete();
            $table->renameColumn('bill_identification', 'missing_invoice_note');
        });

        // Separare la modifica della descrizione in una seconda chiamata Schema::table
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('missing_invoice_note')->nullable()->change()->description('Nota con motivo della mancata fattura');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['contract_id']);
            $table->dropColumn('contract_id');
            $table->dropForeign(['invoice_id']);
            $table->dropColumn('invoice_id');
            $table->renameColumn('missing_invoice_note', 'bill_identification');
        });

        // Separare la modifica della descrizione in una seconda chiamata Schema::table
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('bill_identification')->nullable()->change()->description('Identificativo fattura, se il ticket è stato fatturato');
        });
    }
};
