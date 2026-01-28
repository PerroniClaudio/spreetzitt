<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserDevelopmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::whereIn('name', ['iForTest', 'Az. Cliente 1', 'Az. Cliente 2'])->get();

        foreach ($companies as $company) {
            $companyPrefix = str_replace([' ', '.'], '', strtolower($company->name));
            
            // Admin dell'azienda
            $admin = User::firstOrCreate(
                ['email' => "{$companyPrefix}.admin@test.com"],
                [
                    'name' => 'Admin',
                    'surname' => $company->name,
                    'password' => Hash::make('Password@1'),
                    'is_company_admin' => true,
                    'is_admin' => false,
                    'is_superadmin' => false,
                ]
            );
            
            // Associa l'admin all'azienda se non è già associato
            if (!$admin->companies()->where('company_id', $company->id)->exists()) {
                $admin->companies()->attach($company->id);
            }

            // Primo utente normale
            $user1 = User::firstOrCreate(
                ['email' => "{$companyPrefix}.user1@test.com"],
                [
                    'name' => 'User 1',
                    'surname' => $company->name,
                    'password' => Hash::make('Password@1'),
                    'is_company_admin' => false,
                    'is_admin' => false,
                    'is_superadmin' => false,
                ]
            );
            
            if (!$user1->companies()->where('company_id', $company->id)->exists()) {
                $user1->companies()->attach($company->id);
            }

            // Secondo utente normale
            $user2 = User::firstOrCreate(
                ['email' => "{$companyPrefix}.user2@test.com"],
                [
                    'name' => 'User 2',
                    'surname' => $company->name,
                    'password' => Hash::make('Password@1'),
                    'is_company_admin' => false,
                    'is_admin' => false,
                    'is_superadmin' => false,
                ]
            );
            
            if (!$user2->companies()->where('company_id', $company->id)->exists()) {
                $user2->companies()->attach($company->id);
            }
        }

        // Crea un company_admin multi-azienda con le due aziende clienti
        $companies = Company::whereIn('name', ['Az. Cliente 1', 'Az. Cliente 2'])->get();
    
        $multiCompanyAdmin = User::firstOrCreate(
            ['email' => "multi.company.admin@test.com"],
            [
                'name' => 'Multi Company',
                'surname' => 'Admin',
                'password' => Hash::make('Password@1'),
                'is_company_admin' => true,
                'is_admin' => false,
                'is_superadmin' => false,
            ]
        );
        foreach ($companies as $company) {
            if (!$multiCompanyAdmin->companies()->where('company_id', $company->id)->exists()) {
                $multiCompanyAdmin->companies()->attach($company->id);
            }
        }
    }
}
