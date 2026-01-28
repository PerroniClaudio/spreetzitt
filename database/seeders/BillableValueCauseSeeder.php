<?php

namespace Database\Seeders;

use App\Models\BillableValueCause;
use Illuminate\Database\Seeder;

class BillableValueCauseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $causes = [
            ['name' => 'Contratto di assistenza Full'],
            ['name' => 'Contratto assistenza a ore'],
            ['name' => 'No contratto di assistenza'],
            ['name' => 'Incluso in una fornitura'],
            ['name' => 'Incluso in un progetto'],
        ];

        foreach ($causes as $causeData) {
            // Controlla se esiste già un record con lo stesso nome
            if (BillableValueCause::where('name', $causeData['name'])->exists()) {
                continue;
            }
            
            // Crea il record usando create per i campi fillable
            $cause = BillableValueCause::create([
                'name' => $causeData['name'],
            ]);
        }
    }
}
