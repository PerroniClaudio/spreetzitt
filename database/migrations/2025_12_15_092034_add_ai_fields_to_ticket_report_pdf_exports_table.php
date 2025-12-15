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
        Schema::table('ticket_report_pdf_exports', function (Blueprint $table) {
            $table->boolean('is_ai_generated')->default(false)->after('send_email');
            $table->longText('ai_query')->nullable()->after('is_ai_generated');
            $table->text('ai_prompt')->nullable()->after('ai_query');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_report_pdf_exports', function (Blueprint $table) {
            $table->dropColumn(['is_ai_generated', 'ai_query', 'ai_prompt']);
        });
    }
};
