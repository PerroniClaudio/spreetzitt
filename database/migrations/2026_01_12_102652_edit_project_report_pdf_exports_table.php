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
            $table->string('email_status')->nullable()->after('send_email');
            $table->datetime('last_email_sent_at')->nullable()->after('email_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_report_pdf_exports', function (Blueprint $table) {
            $table->dropColumn('email_status');
            $table->dropColumn('last_email_sent_at');
        });
    }
};
