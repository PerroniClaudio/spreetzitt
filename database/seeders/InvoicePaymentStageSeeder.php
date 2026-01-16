<?php

namespace Database\Seeders;

use App\Models\InvoicePaymentStage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InvoicePaymentStageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stages = [
            [
                'name' => 'Non pagato',
                'description' => 'Fattura non ancora pagata',
                'admin_color' => '#ff9800',
            ],
            [
                'name' => 'Pagato',
                'description' => 'Fattura pagata',
                'admin_color' => '#4caf50',
            ],
            [
                'name' => 'Scaduto',
                'description' => 'Fattura scaduta',
                'admin_color' => '#f44336',
            ],
            [
                'name' => 'Rifiutato',
                'description' => 'Fattura rifiutata',
                'admin_color' => '#e91e63',
            ],
            [
                'name' => 'Richiesto posticipo',
                'description' => 'Richiesto posticipo del pagamento',
                'admin_color' => '#2196f3',
            ],
        ];

        foreach ($stages as $stage) {
            InvoicePaymentStage::firstOrCreate(
                ['name' => $stage['name']],
                $stage
            );
        }
    }
}
