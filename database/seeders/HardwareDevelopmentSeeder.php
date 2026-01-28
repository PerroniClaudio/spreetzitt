<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Hardware;
use App\Models\HardwareType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HardwareDevelopmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Definisci i tipi di hardware disponibili
        $hardwareTypes = [
            'PC' => HardwareType::firstOrCreate(['name' => 'PC']),
            'Smartphone' => HardwareType::firstOrCreate(['name' => 'Smartphone']),
        ];

        // Definisci le marche e modelli per PC
        $pcModels = [
            ['make' => 'Dell', 'model' => 'Latitude 7420'],
            ['make' => 'HP', 'model' => 'EliteBook 840 G8'],
            ['make' => 'Lenovo', 'model' => 'ThinkPad X1 Carbon Gen 9'],
            ['make' => 'ASUS', 'model' => 'ZenBook 14'],
            ['make' => 'Apple', 'model' => 'MacBook Pro 14"'],
            ['make' => 'MSI', 'model' => 'Prestige 14'],
            ['make' => 'Acer', 'model' => 'Swift 3'],
            ['make' => 'Dell', 'model' => 'XPS 13'],
        ];

        // Definisci le marche e modelli per Smartphone
        $smartphoneModels = [
            ['make' => 'Apple', 'model' => 'iPhone 13'],
            ['make' => 'Apple', 'model' => 'iPhone 14 Pro'],
            ['make' => 'Samsung', 'model' => 'Galaxy S23'],
            ['make' => 'Samsung', 'model' => 'Galaxy A54'],
            ['make' => 'Google', 'model' => 'Pixel 7'],
            ['make' => 'Xiaomi', 'model' => 'Redmi Note 12'],
            ['make' => 'OnePlus', 'model' => 'Nord 3'],
            ['make' => 'Oppo', 'model' => 'Find X5'],
        ];

        $companies = Company::all();

        foreach ($companies as $company) {
            // Recupera tutti gli utenti dell'azienda
            $users = User::whereHas('companies', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->get();

            // Per ogni utente, crea un hardware assegnato
            foreach ($users as $user) {
                // Alterna tra PC e Smartphone
                $isPc = rand(0, 1) === 0;
                $hardwareType = $isPc ? $hardwareTypes['PC'] : $hardwareTypes['Smartphone'];
                $models = $isPc ? $pcModels : $smartphoneModels;
                $selectedModel = $models[array_rand($models)];

                $hardware = Hardware::create([
                    'make' => $selectedModel['make'],
                    'model' => $selectedModel['model'],
                    'serial_number' => $this->generateSerialNumber(),
                    'company_asset_number' => 'ASSET-' . strtoupper(Str::random(6)),
                    'support_label' => null,
                    'is_accessory' => false,
                    'purchase_date' => now()->subDays(rand(30, 730)),
                    'company_id' => $company->id,
                    'hardware_type_id' => $hardwareType->id,
                    'ownership_type' => 'company',
                    'ownership_type_note' => null,
                    'notes' => 'Hardware assegnato a ' . $user->name,
                    'is_exclusive_use' => true,
                    'status_at_purchase' => 'new',
                    'status' => 'company',
                    'position' => 'user',
                ]);

                // Assegna l'hardware all'utente
                $hardware->users()->attach($user->id, [
                    'created_by' => 1,
                    'responsible_user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Crea 3 hardware assegnati all'azienda ma non a un utente
            for ($i = 0; $i < 3; $i++) {
                $isPc = rand(0, 1) === 0;
                $hardwareType = $isPc ? $hardwareTypes['PC'] : $hardwareTypes['Smartphone'];
                $models = $isPc ? $pcModels : $smartphoneModels;
                $selectedModel = $models[array_rand($models)];

                Hardware::create([
                    'make' => $selectedModel['make'],
                    'model' => $selectedModel['model'],
                    'serial_number' => $this->generateSerialNumber(),
                    'company_asset_number' => 'ASSET-' . strtoupper(Str::random(6)),
                    'support_label' => null,
                    'is_accessory' => false,
                    'purchase_date' => now()->subDays(rand(30, 730)),
                    'company_id' => $company->id,
                    'hardware_type_id' => $hardwareType->id,
                    'ownership_type' => 'company',
                    'ownership_type_note' => null,
                    'notes' => 'Hardware in magazzino aziendale',
                    'is_exclusive_use' => false,
                    'status_at_purchase' => 'new',
                    'status' => 'company',
                    'position' => 'company',
                ]);
            }
        }
    }

    /**
     * Genera un numero seriale casuale
     */
    protected function generateSerialNumber(): string
    {
        return strtoupper(Str::random(3)) . '-' . rand(1000, 9999) . '-' . strtoupper(Str::random(4));
    }
}
