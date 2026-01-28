<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanyDevelopmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            [
                'name' => 'iForTest',
                'sla_take_low' => 60,
                'sla_take_medium' => 60,
                'sla_take_high' => 60,
                'sla_take_critical' => 60,
                'sla_solve_low' => 600,
                'sla_solve_medium' => 600,
                'sla_solve_high' => 600,
                'sla_solve_critical' => 600,
                'sla' => 'vuoto',
            ],
            [
                'name' => 'Az. Cliente 1',
                'sla_take_low' => 60,
                'sla_take_medium' => 60,
                'sla_take_high' => 60,
                'sla_take_critical' => 60,
                'sla_solve_low' => 600,
                'sla_solve_medium' => 600,
                'sla_solve_high' => 600,
                'sla_solve_critical' => 600,
                'sla' => 'vuoto',
            ],
            [
                'name' => 'Az. Cliente 2',
                'sla_take_low' => 60,
                'sla_take_medium' => 60,
                'sla_take_high' => 60,
                'sla_take_critical' => 60,
                'sla_solve_low' => 600,
                'sla_solve_medium' => 600,
                'sla_solve_high' => 600,
                'sla_solve_critical' => 600,
                'sla' => 'vuoto',
            ],
        ];

        foreach ($companies as $companyData) {
            $company = Company::firstOrCreate(
                ['name' => $companyData['name']],
                $companyData
            );

            $company->offices()->firstOrCreate(
                ['is_legal' => true],
                [
                    'name' => 'Sede Legale',
                    'address' => 'Via Roma',
                    'number' => '1',
                    'zip_code' => '00100',
                    'city' => 'Roma',
                    'province' => 'RM',
                    'latitude' => 41.9028,
                    'longitude' => 12.4964,
                    'is_legal' => true,
                    'is_operative' => true,
                ]
            );
        }
    }
}
