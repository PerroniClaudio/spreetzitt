<?php

namespace Database\Seeders;

use App\Models\HardwareType;
use Illuminate\Database\Seeder;

class HardwareTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hardwareTypes = [
            'PC PORTATILE',
            'PC FISSO',
            'SMARTPHONE',
            'TABLET',
            'STAMPANTE LOCALE',
            'STAMPANTE DI RETE',
            'SIM CARD',
            'MONITOR',
            'SERVER FISICO',
            'SERVER VIRTUALE',
            'SERVER IN DATA CENTER',
            'PC FISSO MAC',
            'PC PORTATILE MAC',
            'DOCKING STATION',
            'SCANNER LOCALE',
            'SCANNER DI RETE',
            'TELEFONO CELLULARE',
            'SWITCH',
            'FIREWALL',
            'ACCESS POINT',
            'CUFFIE',
            'WEBCAM',
            'MOUSE NORMALE',
            'MOUSE ERGONOMICO',
            'TASTIERA ITALIANA',
            'TASTIERA SPAGNOLA',
            'TASTIERA TEDESCA',
            'TASTIERA INGLESE',
            'TAPPETINO ERGONOMICO',
            'ADATTATORE WIFI',
            'ADATTATORE RJ45',
        ];

        foreach ($hardwareTypes as $typeName) {
            HardwareType::firstOrCreate(
                ['name' => $typeName]
            );
        }
    }
}
