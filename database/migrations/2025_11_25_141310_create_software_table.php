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
        Schema::create('software', function (Blueprint $table) {
            $table->id();
            $table->string('vendor');
            $table->string('product_name');
            $table->string('version')->nullable();
            $table->string('activation_key')->nullable();
            $table->string('company_asset_number')->nullable()->unique();
            $table->boolean('is_exclusive_use')->default(false);
            $table->string('license_type')->nullable(); // perpetua, abbonamento, trial, open-source
            $table->integer('max_installations')->nullable();
            $table->timestamp('purchase_date')->nullable();
            $table->timestamp('expiration_date')->nullable();
            $table->timestamp('support_expiration_date')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('software_type_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
            $table->foreign('software_type_id')->references('id')->on('software_types')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('software');
    }
};
