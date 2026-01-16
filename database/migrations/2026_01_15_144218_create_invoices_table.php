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

        Schema::create('invoice_payment_stages', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->string('admin_color')->default('#ffffff')->comment('Color displayed to administrators in the UI');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
            $table->string('number')->unique();
            $table->text('description')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_stage_id')
                ->nullable()
                ->constrained('invoice_payment_stages')
                ->nullOnDelete();
            $table->date('invoice_date');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_payment_stages');
    }
};