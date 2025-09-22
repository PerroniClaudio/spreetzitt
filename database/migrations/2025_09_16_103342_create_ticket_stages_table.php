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
        Schema::create('ticket_stages', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('admin_color')->default('#ffffff')->comment('Color displayed to administrators in the UI');
            $table->string('user_color')->default('#ffffff')->comment('Color displayed to end users in the UI');
            $table->integer('order')->default(0)->comment('Defines the order of the stages in which they appear in the UI');
            $table->boolean('is_sla_pause')->default(true)->comment('Indicates if the SLA timer should be paused in this stage');
            $table->boolean('is_system')->default(false)->comment('Se TRUE, lo stage è di sistema e non può essere eliminato');
            $table->string('system_key')->nullable()->unique()->comment('Chiave di sistema per identificare stage speciali (new, closed, etc.), lasciando modificabile il nome visualizzato');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_stages');
    }
};
