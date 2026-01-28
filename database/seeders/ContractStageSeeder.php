<?php

namespace Database\Seeders;

use App\Models\ContractStage;
use App\Models\InvoicePaymentStage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ContractStageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stages = [
            [
                'name' => 'Preparazione proposta',
                'description' => 'Proposta in fase di preparazione',
                'admin_color' => '#ff9800',
            ],
            [
                'name' => 'Attesa conferma cliente',
                'description' => 'Proposta in attesa di conferma da parte del cliente',
                'admin_color' => '#4caf50',
            ],
            [
                'name' => 'Accettato dal cliente',
                'description' => 'Proposta accettata dal cliente',
                'admin_color' => '#f44336',
            ],
            [
                'name' => 'In corso',
                'description' => 'Contratto in corso',
                'admin_color' => '#e91e63',
            ],
            [
                'name' => 'Scaduto',
                'description' => 'Contratto scaduto',
                'admin_color' => '#2196f3',
            ],
        ];

        foreach ($stages as $stage) {
            // Controlla se esiste già un record con lo stesso nome
            if (ContractStage::where('name', $stage['name'])->exists()) {
                continue;
            }
            
            ContractStage::firstOrCreate(
                ['name' => $stage['name']],
                $stage
            );
        }
    }
}
