<?php

namespace Database\Seeders;

use App\Models\SoftwareType;
use Illuminate\Database\Seeder;

class SoftwareTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $softwareTypes = [
            'OFFICE AUTOMATION',
            'CAD CAM',
            'APPLICAZIONE GRAFICA',
            'ENTI PUBBLICI',
        ];

        foreach ($softwareTypes as $typeName) {
            SoftwareType::firstOrCreate(
                ['name' => $typeName]
            );
        }
    }
}
