<?php

namespace Database\Seeders;

use App\Models\TicketCause;
use Illuminate\Database\Seeder;

class TicketCauseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $causes = [
            ['name' => 'Errore del cliente in inserimento dati'],
            ['name' => 'Errore cliente nell\'uso dell\'applicazione'],
            ['name' => 'Cliente non formato sull\'applicazione'],
            ['name' => 'Errore dell\'applicazione'],
            ['name' => 'Cambio necessità del cliente'],
            ['name' => 'Nuova necessità del cliente'],
            ['name' => 'Errore di altro servizio di supporto'],
            ['name' => 'Errore del Supporto iFortech'],
            ['name' => 'Agg. sicurezza vendor'],
            ['name' => 'Supporto a terza parte cliente']
        ];

        foreach ($causes as $causeData) {
            // Crea il record usando create per i campi fillable
            $cause = TicketCause::firstOrCreate(
                ['name' => $causeData['name']]
            );
        }
    }
}
