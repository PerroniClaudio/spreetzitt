<?php

namespace Database\Seeders;

use App\Models\TicketStage;
use Illuminate\Database\Seeder;

class TicketStageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stages = [
            [
                'name' => 'Nuovo',
                'description' => 'Ticket creato, in attesa di presa in carico',
                'admin_color' => '#e01b24',
                'user_color' => '#e01b24',
                'order' => 1,
                'is_sla_pause' => false, // Il timer SLA inizia subito
                'is_system' => true,
                'system_key' => 'new',
            ],
            [
                'name' => 'Assegnato',
                'description' => 'Ticket assegnato a un operatore',
                'admin_color' => '#ff7800',
                'user_color' => '#ff7800',
                'order' => 2,
                'is_sla_pause' => false, // SLA continua
                'is_system' => true,
                'system_key' => 'assigned',
            ],
            [
                'name' => 'In corso',
                'description' => 'Operatore sta lavorando al ticket',
                'admin_color' => '#f6d32d',
                'user_color' => '#f6d32d',
                'order' => 3,
                'is_sla_pause' => false, // SLA continua
                'is_system' => true,
                'system_key' => 'in_progress',
            ],
            [
                'name' => 'In attesa',
                'description' => 'Ticket in attesa di risorse o informazioni interne o da terzi',
                'admin_color' => '#3584e4',
                'user_color' => '#3584e4',
                'order' => 4,
                'is_sla_pause' => true, // SLA in pausa
                'is_system' => true,
                'system_key' => 'waiting',
            ],
            [
                'name' => 'Attesa feedback cliente',
                'description' => 'In attesa di risposta o feedback dal cliente',
                'admin_color' => '#3584e4',
                'user_color' => '#3584e4',
                'order' => 5,
                'is_sla_pause' => true, // SLA in pausa (dipende dal cliente)
                'is_system' => true,
                'system_key' => 'waiting_user',
            ],
            [
                'name' => 'Risolto',
                'description' => 'Per il supporto è risolto, in attesa di conferma dal cliente per procedere alla chiusura',
                'admin_color' => '#33d17a',
                'user_color' => '#33d17a',
                'order' => 6,
                'is_sla_pause' => true, // SLA fermato, risoluzione completata
                'is_system' => false,
                'system_key' => null,
            ],
            [
                'name' => 'Chiuso',
                'description' => 'Chiuso definitivamente',
                'admin_color' => '#c0bfbc',
                'user_color' => '#c0bfbc',
                'order' => 7,
                'is_sla_pause' => true, // SLA completamente fermato
                'is_system' => true,
                'system_key' => 'closed',
            ],
        ];

        foreach ($stages as $stageData) {
            // Crea il record usando create per i campi fillable
            $stage = TicketStage::firstOrCreate(
                ['name' => $stageData['name']],
                [
                    'description' => $stageData['description'],
                    'admin_color' => $stageData['admin_color'],
                    'user_color' => $stageData['user_color'],
                    'order' => $stageData['order'],
                    'is_sla_pause' => $stageData['is_sla_pause'],
                    'is_system' => $stageData['is_system'],
                ]
            );

            // Imposta system_key separatamente se necessario (non è fillable)
            if (! is_null($stageData['system_key'])) {
                $stage->system_key = $stageData['system_key'];
                $stage->save();
            }
        }
    }
}
