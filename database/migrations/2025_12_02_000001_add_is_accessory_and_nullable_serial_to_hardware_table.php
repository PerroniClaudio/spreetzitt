<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hardware', function (Blueprint $table) {
            // add is_accessory flag
            $table->boolean('is_accessory')->default(false)->after('position');
        });

        // Make serial_number nullable and add unique index
        // We need to use raw statements for altering column nullable in some DB engines
        if (Schema::hasColumn('hardware', 'serial_number')) {
            $connection = Schema::getConnection();
            $driver = $connection->getDriverName();

            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `hardware` MODIFY `serial_number` VARCHAR(255) NULL');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE "hardware" ALTER COLUMN "serial_number" DROP NOT NULL');
            } else {
                // SQLite and others: attempt to change via schema builder (may require sqlite recreation in tests)
                Schema::table('hardware', function (Blueprint $table) {
                    $table->string('serial_number')->nullable()->change();
                });
            }
        }

        // Ensure existing rows are marked as hardware (not accessory)
        DB::table('hardware')->update(['is_accessory' => false]);

        // Create unique index on serial_number (multiple NULLs allowed on supported DBs)
        Schema::table('hardware', function (Blueprint $table) {
            $table->unique('serial_number', 'hardware_serial_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hardware', function (Blueprint $table) {
            // drop unique index
            $table->dropUnique('hardware_serial_number_unique');
        });

        // attempt to set serial_number back to not null (beware of null values)
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `hardware` MODIFY `serial_number` VARCHAR(255) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "hardware" ALTER COLUMN "serial_number" SET NOT NULL');
        } else {
            Schema::table('hardware', function (Blueprint $table) {
                $table->string('serial_number')->nullable(false)->change();
            });
        }

        Schema::table('hardware', function (Blueprint $table) {
            $table->dropColumn('is_accessory');
        });
    }
};
