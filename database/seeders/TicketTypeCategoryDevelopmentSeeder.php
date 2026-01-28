<?php

namespace Database\Seeders;

use App\Models\TicketTypeCategory;
use Illuminate\Database\Seeder;

class TicketTypeCategoryDevelopmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Microsoft 365',                    // normali MS365 per op. strutt.
            'Sistema Operativo',                // normali
            'Hardware',                         // normali HW
            'Sito Web',                         // normali
            'Assistenza Dedicata',              // attività programmate
            'Sviluppo',                         // normali
            'Assistenza Sistemistica',          // normali
            'Formazione',                       // normali
            'Creazione nuova utenza',           // operazioni strutturate
            'Progetti',                         // progetti
            'Chiusura utenza',                  // operazioni strutturate
            'Dominio locale - Active Directory', // normali
        ];

        foreach ($categories as $categoryName) {
            // Crea record con is_problem = true, is_request = false
            TicketTypeCategory::firstOrCreate(
                [
                    'name' => $categoryName,
                    'is_problem' => true,
                    'is_request' => false,
                ]
            );

            // Crea record con is_problem = false, is_request = true
            TicketTypeCategory::firstOrCreate(
                [
                    'name' => $categoryName,
                    'is_problem' => false,
                    'is_request' => true,
                ]
            );
        }
    }
}
