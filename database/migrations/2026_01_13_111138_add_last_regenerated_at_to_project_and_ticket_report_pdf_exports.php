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
        Schema::table('project_report_pdf_exports', function (Blueprint $table) {
            $table->timestamp('last_regenerated_at')->nullable()->after('last_email_sent_at');
        });

        Schema::table('ticket_report_pdf_exports', function (Blueprint $table) {
            $table->timestamp('last_regenerated_at')->nullable()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_report_pdf_exports', function (Blueprint $table) {
            $table->dropColumn('last_regenerated_at');
        });

        Schema::table('ticket_report_pdf_exports', function (Blueprint $table) {
            $table->dropColumn('last_regenerated_at');
        });
    }
};
