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
        Schema::create('vertex_ai_query_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('user_email')->nullable(); // Backup dell'email in caso l'utente venga eliminato
            $table->text('user_prompt'); // Il prompt originale dell'utente
            $table->text('generated_sql')->nullable(); // La query SQL generata dall'AI
            $table->text('ai_response')->nullable(); // Risposta completa dell'AI
            $table->integer('result_count')->nullable(); // Numero di righe restituite
            $table->boolean('was_successful')->default(false); // Se l'operazione è andata a buon fine
            $table->text('error_message')->nullable(); // Eventuale messaggio di errore
            $table->string('ip_address')->nullable(); // IP dell'utente
            $table->text('user_agent')->nullable(); // User agent del browser
            $table->decimal('execution_time', 8, 3)->nullable(); // Tempo di esecuzione in secondi
            $table->timestamps();

            // Indici per performance
            $table->index(['user_id', 'created_at']);
            $table->index(['was_successful', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vertex_ai_query_logs');
    }
};
