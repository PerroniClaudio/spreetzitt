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
        Schema::table('type_form_fields', function (Blueprint $table) {
            $table->integer('property_limit')->nullable()->after('include_no_type_hardware');
            $table->boolean('include_no_type_property')->nullable()->after('property_limit');
            $table->text('property_types_list')->nullable()->after('include_no_type_property');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('type_form_fields', function (Blueprint $table) {
            $table->dropColumn(['property_limit', 'include_no_type_property', 'property_types_list']);
        });
    }
};
