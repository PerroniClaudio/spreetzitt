<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('surname')->nullable();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->unsignedBigInteger('company_id')->nullable();
            $table->boolean('is_company_admin')->default(false);

            // Chiave esterna per l'id della compagnia (companies)
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('set null'); // o qualsiasi altra azione tu voglia eseguire alla cancellazione dell'azienda
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('surname');
            $table->dropColumn('phone');
            $table->dropColumn('city');
            $table->dropColumn('zip_code');
            $table->dropColumn('address');
            $table->dropColumn('is_admin');
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
            $table->dropColumn('is_company_admin');
        });
    }
};
