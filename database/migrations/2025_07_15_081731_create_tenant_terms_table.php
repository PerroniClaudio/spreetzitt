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
        Schema::create('tenant_terms', function (Blueprint $table) {
            $table->id();
            $table->string('tenant')->index();
            $table->string('key')->index();
            $table->json('value');
            $table->text('description')->nullable();
            $table->string('category')->nullable()->index();
            $table->timestamps();

            $table->unique(['tenant', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_terms');
    }
};
