<?php

namespace Database\Seeders;

use App\Models\Group;
use Illuminate\Database\Seeder;

class GroupDevelopmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $groups = [
            [
                'name' => 'Sviluppo Web',
                'email' => 'sviluppo.web@example.com',
            ],
            [
                'name' => 'Marketing',
                'email' => 'marketing@example.com',
            ],
            [
                'name' => 'Windows',
                'email' => 'windows@example.com',
            ],
            [
                'name' => 'Sistemi',
                'email' => 'sistemi@example.com',
            ],
            [
                'name' => 'DPO',
                'email' => 'dpo@example.com',
            ],
            [
                'name' => 'Consulenze',
                'email' => 'consulenze@example.com',
            ],
            [
                'name' => 'Ufficio Commerciale',
                'email' => 'ufficio.commerciale@example.com',
            ],
            [
                'name' => 'Customer Care',
                'email' => 'customer.care@example.com',
            ],
        ];

        foreach ($groups as $groupData) {
            // Crea il record usando create per i campi fillable
            $group = Group::firstOrCreate(
                ['name' => $groupData['name']],
                [
                    'email' => $groupData['email'],
                ]
            );
        }
    }
}
