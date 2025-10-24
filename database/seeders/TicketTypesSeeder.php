<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\TicketType;
use App\Models\TicketTypeCategory;
use App\Models\TypeFormFields;
use Illuminate\Database\Seeder;

class TicketTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Controllo perchè poi ci potrà essere un seeder delle categorie che l'ha già creata.
        $isCategoryPresent = TicketTypeCategory::where('name', 'Hardware')->exists();
        if (! $isCategoryPresent) {
            TicketTypeCategory::create([
                'name' => 'Hardware',
                'is_problem' => false,
                'is_request' => true,
            ]);
        }
        $ticketTypeCategory = TicketTypeCategory::where('name', 'Hardware')->first();

        $newHardwareTicketType = TicketType::create([
            'name' => 'Richiesta caricamento hardware',
            'ticket_type_category_id' => $ticketTypeCategory->id,
            'default_priority' => 'medium',
            'default_sla_take' => 240,
            'default_sla_solve' => 3000,
            'it_referer_limited' => true,
            'is_massive_enabled' => false,
            'description' => 'Usarlo per richiedere il caricamento di nuovo hardware nel gestionale',
            'warning' => 'A ticket aperto allegare il file con i dati da caricare. il template è disponibile nella sezione hardware del sito.',
            'expected_processing_time' => 30,
        ]);
        $dissociateHardwareUserType = TicketType::create([
            'name' => 'Richiesta eliminazione associazione hardware-utente',
            'ticket_type_category_id' => $ticketTypeCategory->id,
            'default_priority' => 'medium',
            'default_sla_take' => 240,
            'default_sla_solve' => 3000,
            'it_referer_limited' => true,
            'is_massive_enabled' => false,
            'description' => 'Usarlo per richiedere l\'eliminazione dell\'associazione tra hardware e utente.',
            'warning' => '',
            'expected_processing_time' => 30,
        ]);

        // Prima di assegnarli all'azienda devono essere associati a un gruppo (altrimenti non vengono visualizzati lato admin)
        // Controllo perchè poi ci potrà essere un seeder dei gruppi che l'ha già creato.
        $isGroupPresent = Group::where('name', 'Sistemi')->exists();
        if (! $isGroupPresent) {
            Group::create([
                'name' => 'Sistemi',
            ]);
        }
        $group = Group::where('name', 'Sistemi')->first();
        $group->ticketTypes()->attach($newHardwareTicketType->id);
        $group->ticketTypes()->attach($dissociateHardwareUserType->id);

        // Va creato il form per i ticket type.
        // Penso che basti inserire l'avviso di allegare il file  (magari scaricando prima il template dal gestionale, ancora da fare)

        TypeFormFields::create([
            'ticket_type_id' => $dissociateHardwareUserType->id,
            'field_name' => 'dispositivo',
            'field_label' => 'Dispositivo',
            'field_type' => 'hardware',
            'required' => true,
            'placeholder' => 'Selezionare il dispositivo della richiesta',
            'hardware_limit' => 1,
            'include_no_type_hardware' => true,
        ]);
        TypeFormFields::create([
            'ticket_type_id' => $dissociateHardwareUserType->id,
            'field_name' => 'id_utenti_da_dissociare_(separati_da_virgola)',
            'field_label' => 'ID utenti da dissociare (separati da virgola)',
            'field_type' => 'textarea',
            'required' => true,
            'placeholder' => 'Inserire gli ID degli utenti separati da virgola (es. 12, 15, 35)',
        ]);

    }
}
