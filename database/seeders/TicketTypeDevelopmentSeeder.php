<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Group;
use App\Models\TicketType;
use App\Models\TicketTypeCategory;
use App\Models\TypeFormFields;
use Illuminate\Database\Seeder;

class TicketTypeDevelopmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $groups = 'Sviluppo Web', 'Marketing', 'Windows', 'Sistemi', 'DPO', 'Consulenze', 'Ufficio Commerciale', 'Customer Care';

        // 'fields' => [
        //     [
        //         'field_name' => '',
        //         'field_type' => '',
        //         'field_label' => '',
        //         'required' => false,
        //         'description' => '',
        //         'placeholder' => '',
        //         'options' => null,
        //         'hardware_limit' => null,
        //         'include_no_type_hardware' => 0,
        //     ]
        // ]

        $categories = [
            [
                'name' => 'Microsoft 365', // normali MS365 per op. strutt.
                'problems' => [
                    [
                        'name' => 'OneDrive - Problema sincronizzazione',
                        'description' => 'Problemi di sincronizzazione con OneDrive',
                        'default_priority' => 'medium',
                        'default_sla_take' => 60,
                        'default_sla_solve' => 600,
                        'groups' => ['Windows'],
                        'fields' => [
                            [
                                'field_name' => 'user_name_12d',
                                'field_type' => 'text',
                                'field_label' => 'Nome Utente',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire l\'utenza',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'pc_name_dw2',
                                'field_type' => 'text',
                                'field_label' => 'Nome PC',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire il nome del PC',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'phone_number_9k3',
                                'field_type' => 'tel',
                                'field_label' => 'Numero di Telefono',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire il numero di telefono',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                        ],
                    ],
                ],
                'requests' => [
                    [
                        'name' => 'Utenza 365 - Apertura Nuova - Chiusura - Variazione',
                        'description' => '',
                        'default_priority' => 'medium',
                        'default_sla_take' => 60,
                        'default_sla_solve' => 600,
                        'groups' => ['Windows'],
                        'fields' => [
                            [
                                'field_name' => 'request_type_456',
                                'field_type' => 'select',
                                'field_label' => 'Tipo di Richiesta',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire il tipo di richiesta',
                                'options' => 'Creazione Utenza; Cancellazione Utenza esistente; Modifica Utenza esistente',
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'user_name_112d',
                                'field_type' => 'text',
                                'field_label' => 'Nome e cognome nuovo utente',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire il nome e cognome del nuovo utente',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'start_date_789',
                                'field_type' => 'date',
                                'field_label' => 'Data di decorrenza',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire la data di decorrenza',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'backup_mailbox_321',
                                'field_type' => 'select',
                                'field_label' => 'Effettua il backup cassetta postale',
                                'required' => false,
                                'description' => '',
                                'placeholder' => 'Indicare solo per eliminazione utenza',
                                'options' => 'Si; No',
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'email_654',
                                'field_type' => 'email',
                                'field_label' => 'Email utente',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire l\'email dell\'utente',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'out_of_office_987',
                                'field_type' => 'radio',
                                'field_label' => 'Serve il messaggio fuori sede?',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Specificare se serve il messaggio fuori sede',
                                'options' => 'Si; No',
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'out_of_office_message_741',
                                'field_type' => 'text',
                                'field_label' => 'Messaggio fuori sede (se necessario)',
                                'required' => false,
                                'description' => '',
                                'placeholder' => 'Inserire il messaggio fuori sede',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'deactivation_time_258',
                                'field_type' => 'text',
                                'field_label' => 'Orario decorrenza (se cancellazione)',
                                'required' => false,
                                'description' => '',
                                'placeholder' => 'Inserire l\'orario di decorrenza',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                        ],
                    ],
                    [
                        'name' => 'Mailbox condivisa - Creazione/Modifica/Eliminazione',
                        'description' => '',
                        'default_priority' => 'low',
                        'default_sla_take' => 120,
                        'default_sla_solve' => 1440,
                        'groups' => ['Windows'],
                        'fields' => [
                            [
                                'name' => 'request_type_aa1',
                                'field_type' => 'select',
                                'field_label' => 'Tipo di Richiesta',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire il tipo di richiesta',
                                'options' => 'Creazione Mailbox Condivisa; Cancellazione Mailbox Condivisa; Aggiunta utente; Rimozione utente',
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'name' => 'mailbox_name_bb2',
                                'field_type' => 'text',
                                'field_label' => 'Nome Mailbox Condivisa',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire il nome della mailbox condivisa',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'name' => 'user_email_cc3',
                                'field_type' => 'email',
                                'field_label' => 'Email Utente da Aggiungere/Rimuovere',
                                'required' => false,
                                'description' => '',
                                'placeholder' => 'Inserire l\'email dell\'utente',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'permission_type_dd4',
                                'field_type' => 'select',
                                'field_label' => 'Tipo di abilitazione',
                                'required' => false,
                                'description' => '',
                                'placeholder' => 'Selezionare il tipo di abilitazione',
                                'options' => 'Lettura e gestione; Invia come; Invia per conto di',
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                        ],
                    ],
                    [
                        'name' => 'Abilitazione o disabilitazione cartella OneDrive',
                        'description' => 'Richiesta di abilitazione o disabilitazione di una cartella condivisa in OneDrive',
                        'default_priority' => 'low',
                        'default_sla_take' => 240,
                        'default_sla_solve' => 4320,
                        'groups' => ['Windows'],
                        'fields' => [
                            [
                                'field_name' => 'user_email_odi0',
                                'field_type' => 'email',
                                'field_label' => 'Email Utente',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire l\'email dell\'utente',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'operation_type_odi1',
                                'field_type' => 'select',
                                'field_label' => 'Tipo di abilitazione',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Selezionare il tipo di abilitazione',
                                'options' => 'Visualizzazione con download; Visualizzazione senza download; Controllo completo; Disattivazione',
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'folder_path_odi2',
                                'field_type' => 'text',
                                'field_label' => 'Percorso Cartella',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire il percorso della cartella in OneDrive',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Hardware', // normali
                'problems' => [
                    [
                        'name' => 'Problema Hardware',
                        'description' => 'Segnalazione di un problema hardware relativo a un dispositivo',
                        'default_priority' => 'high',
                        'default_sla_take' => 120,
                        'default_sla_solve' => 1440,
                        'groups' => ['Sistemi'],
                        'fields' => [
                            [
                                'field_name' => 'problematic_hardware_001',
                                'field_type' => 'hardware',
                                'field_label' => 'Quale dispositivo presenta il problema?',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Selezionare il dispositivo con il problema',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'others_002',
                                'field_type' => 'textarea',
                                'field_label' => 'Altro campo facoltativo',
                                'required' => false,
                                'description' => '',
                                'placeholder' => 'Altra info facoltativa',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                        ],
                    ],
                ],
                'requests' => [
                    [
                        'name' => 'Assegnazione nuovo hardware/ritiro hardware',
                        'description' => 'Richiesta di assegnazione di nuovo hardware o ritiro di hardware esistente',
                        'default_priority' => 'medium',
                        'default_sla_take' => 240,
                        'default_sla_solve' => 3000,
                        'groups' => ['Sistemi'],
                        'fields' => [
                            [
                                'field_name' => 'new_hardware_003',
                                'field_type' => 'hardware',
                                'field_label' => 'Selezionare hardware da ritirare/consegnare',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Selezionare il dispositivo',
                                'options' => null,
                                'hardware_limit' => 1,
                                'include_no_type_hardware' => 1,
                            ],
                            [
                                'field_name' => 'user_name_004',
                                'field_type' => 'text',
                                'field_label' => 'Nome utente',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire il nome utente',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'others_0dv4',
                                'field_type' => 'textarea',
                                'field_label' => 'Altro campo facoltativo',
                                'required' => false,
                                'description' => '',
                                'placeholder' => 'Altra info facoltativa',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Sito Web',                // normali
                'problems' => [
                    [
                        'name' => 'Anomalia Sito Web',
                        'description' => 'Segnalazione di un problema relativo al sito web',
                        'default_priority' => 'low',
                        'default_sla_take' => 60,
                        'default_sla_solve' => 720,
                        'groups' => ['Sviluppo Web'],
                        'fields' => [],
                    ],
                ],
                'requests' => [
                    [
                        'name' => 'Richiesta Modifica Sito Web',
                        'description' => 'Richiesta di modifica o aggiornamento del sito web',
                        'default_priority' => 'medium',
                        'default_sla_take' => 120,
                        'default_sla_solve' => 1440,
                        'groups' => ['Sviluppo Web'],
                        'fields' => [],
                    ],
                ],
            ],
            [
                'name' => 'Assistenza Dedicata',              // attività programmate
                'requests' => [
                    [
                        'name' => 'Richiesta di assistenza dedicata',
                        'description' => 'Richiesta di assistenza dedicata',
                        'default_priority' => 'low',
                        'default_sla_take' => 240,
                        'default_sla_solve' => 4320,
                        'is_scheduling' => true,
                        'groups' => ['Sistemi'],
                        'fields' => [],
                    ],
                ],
            ],
            [
                'name' => 'Assistenza Sistemistica',          // normali
                'problems' => [
                    [
                        'name' => 'Google Cloud - Problema Servizio',
                        'description' => 'Segnalazione di un problema con un servizio Google Cloud',
                        'default_priority' => 'high',
                        'default_sla_take' => 120,
                        'default_sla_solve' => 1440,
                        'groups' => ['Sistemi'],
                        'fields' => [],
                    ],
                ],
            ],
            [
                'name' => 'Formazione',                       // normali
                'requests' => [
                    [
                        'name' => 'Richiesta di formazione utente',
                        'description' => 'Richiesta di sessione di formazione',
                        'default_priority' => 'low',
                        'default_sla_take' => 240,
                        'default_sla_solve' => 4320,
                        'groups' => ['Marketing'],
                        'fields' => [
                            [
                                'field_name' => 'user_to_train_555',
                                'field_type' => 'text',
                                'field_label' => 'Nome utente da formare',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire il nome utente da formare',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Dominio locale - Active Directory', // normali
                'problems' => [
                    [
                        'name' => 'Problema Dominio Locale - Active Directory',
                        'description' => 'Segnalazione di un problema relativo al dominio locale o Active Directory',
                        'default_priority' => 'high',
                        'default_sla_take' => 120,
                        'default_sla_solve' => 1440,
                        'groups' => ['Windows'],
                        'fields' => [],
                    ],
                ],
                'requests' => [
                    [
                        'name' => 'Creazione/Modifica/Eliminazione Utenza Dominio Locale - Active Directory',
                        'description' => 'Richiesta di modifica utenza in dominio locale o Active Directory',
                        'default_priority' => 'medium',
                        'default_sla_take' => 240,
                        'default_sla_solve' => 3000,
                        'groups' => ['Windows'],
                        'fields' => [
                            [
                                'field_name' => 'operation_type_adi1',
                                'field_type' => 'select',
                                'field_label' => 'Tipo di Operazione',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Selezionare il tipo di operazione',
                                'options' => 'Creazione Utenza; Modifica Utenza Esistente; Eliminazione Utenza',
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'user_full_name_adi2',
                                'field_type' => 'text',
                                'field_label' => 'Nome e Cognome Utente',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire il nome e cognome dell\'utente',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Creazione nuova utenza',           // operazioni strutturate
                'requests' => [
                    [
                        'name' => 'Creazione nuova utenza',
                        'description' => 'Richiesta di creazione di una nuova utenza',
                        'default_priority' => 'medium',
                        'default_sla_take' => 60,
                        'default_sla_solve' => 600,
                        'is_master' => true,
                        'groups' => ['Windows'],
                        'fields' => [
                            [
                                'field_name' => 'new_user_full_name_777',
                                'field_type' => 'text',
                                'field_label' => 'Nome e Cognome Nuovo Utente',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire il nome e cognome del nuovo utente',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'new_user_email_888',
                                'field_type' => 'email',
                                'field_label' => 'Email Nuovo Utente',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire l\'email del nuovo utente',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                            [
                                'field_name' => 'new_user_start_date_999',
                                'field_type' => 'date',
                                'field_label' => 'Data di Inizio',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire la data di inizio',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                        ],
                        'slave_ticket_types' => [
                            [
                                'type_name' => 'Creazione/Modifica/Eliminazione Utenza Dominio Locale - Active Directory',
                                'required' => true,
                            ],
                            [
                                'type_name' => 'Utenza 365 - Apertura Nuova - Chiusura - Variazione',
                                'required' => true,
                            ],
                            [
                                'type_name' => 'Hardware - Assegnazione nuovo hardware/ritiro hardware',
                                'required' => false,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Progetti',                         // progetti
                'requests' => [
                    [
                        'name' => 'Progetto',
                        'description' => 'Gestione progetto',
                        'default_priority' => 'medium',
                        'default_sla_take' => 240,
                        'default_sla_solve' => 4320,
                        'is_project' => true,
                        'groups' => ['Sistemi'],
                        'fields' => [],
                    ],
                ],
            ],
            [
                'name' => 'Chiusura utenza',                  // operazioni strutturate
                'requests' => [
                    [
                        'name' => 'Chiusura utenza',
                        'description' => 'Richiesta di chiusura di un\'utenza',
                        'default_priority' => 'medium',
                        'default_sla_take' => 60,
                        'default_sla_solve' => 600,
                        'is_master' => true,
                        'groups' => ['Windows'],
                        'fields' => [
                            [
                                'field_name' => 'user_to_close_333',
                                'field_type' => 'text',
                                'field_label' => 'Nome e Cognome Utente da Chiudere',
                                'required' => true,
                                'description' => '',
                                'placeholder' => 'Inserire il nome e cognome dell\'utente da chiudere',
                                'options' => null,
                                'hardware_limit' => null,
                                'include_no_type_hardware' => 0,
                            ],
                        ],
                        'slave_ticket_types' => [
                            [
                                'type_name' => 'Creazione/Modifica/Eliminazione Utenza Dominio Locale - Active Directory',
                                'required' => true,
                            ],
                            [
                                'type_name' => 'Utenza 365 - Apertura Nuova - Chiusura - Variazione',
                                'required' => true,
                            ],
                            [
                                'type_name' => 'Hardware - Assegnazione nuovo hardware/ritiro hardware',
                                'required' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Recupera tutte le aziende
        $companies = Company::all();

        foreach ($companies as $company) {
            $this->processCategories($categories, $company);
        }
    }

    /**
     * Processa tutte le categorie per una specifica azienda
     */
    protected function processCategories(array $categories, Company $company): void
    {
        foreach ($categories as $categoryData) {
            // Recupera o crea la categoria
            $category = TicketTypeCategory::firstOrCreate([
                'name' => $categoryData['name'],
            ]);

            // Processa i problemi (problems)
            if (isset($categoryData['problems'])) {
                foreach ($categoryData['problems'] as $problemData) {
                    $this->createTicketType($problemData, $category, $company, true, false);
                }
            }

            // Processa le richieste (requests)
            if (isset($categoryData['requests'])) {
                foreach ($categoryData['requests'] as $requestData) {
                    $this->createTicketType($requestData, $category, $company, false, true);
                }
            }
        }
    }

    /**
     * Crea o aggiorna un tipo di ticket
     */
    protected function createTicketType(
        array $typeData,
        TicketTypeCategory $category,
        Company $company,
        bool $isProblem,
        bool $isRequest
    ): TicketType {
        // Prepara i dati base per firstOrCreate
        $searchCriteria = [
            'name' => $typeData['name'],
            'company_id' => $company->id,
        ];

        // Prepara i dati aggiuntivi per la creazione
        $additionalData = [
            'ticket_type_category_id' => $category->id,
            'description' => $typeData['description'] ?? '',
            'default_priority' => $typeData['default_priority'] ?? 'medium',
            'default_sla_take' => $typeData['default_sla_take'] ?? 120,
            'default_sla_solve' => $typeData['default_sla_solve'] ?? 1440,
            'is_master' => $typeData['is_master'] ?? false,
            'is_scheduling' => $typeData['is_scheduling'] ?? false,
            'is_project' => $typeData['is_project'] ?? false,
        ];

        // Crea o recupera il tipo di ticket
        $ticketType = TicketType::firstOrCreate($searchCriteria, $additionalData);

        // Se il ticket type esiste già, aggiorna i campi (opzionale)
        if (! $ticketType->wasRecentlyCreated) {
            $ticketType->update($additionalData);
        }

        // Associa i gruppi
        if (isset($typeData['groups']) && ! empty($typeData['groups'])) {
            $this->attachGroups($ticketType, $typeData['groups']);
        }

        // Processa i campi del webform
        if (isset($typeData['fields']) && ! empty($typeData['fields'])) {
            $this->processFormFields($ticketType, $typeData['fields']);
        }

        // Se è un tipo master, collega i ticket slave
        if (isset($typeData['is_master']) && $typeData['is_master'] === true) {
            if (isset($typeData['slave_ticket_types']) && ! empty($typeData['slave_ticket_types'])) {
                $this->attachSlaveTicketTypes($ticketType, $typeData['slave_ticket_types'], $company);
            }
        }

        return $ticketType;
    }

    /**
     * Associa i gruppi al tipo di ticket
     */
    protected function attachGroups(TicketType $ticketType, array $groupNames): void
    {
        $groupIds = Group::whereIn('name', $groupNames)->pluck('id')->toArray();

        if (! empty($groupIds)) {
            $ticketType->groups()->sync($groupIds);
        }
    }

    /**
     * Processa e crea i campi del webform
     */
    protected function processFormFields(TicketType $ticketType, array $fields): void
    {
        // Elimina i campi esistenti per questo tipo di ticket
        TypeFormFields::where('ticket_type_id', $ticketType->id)->delete();

        foreach ($fields as $index => $fieldData) {
            // Gestisce il nome del campo (field_name o name)
            $fieldName = $fieldData['field_name'] ?? $fieldData['name'] ?? null;

            if (! $fieldName) {
                continue; // Salta se non c'è un nome campo
            }

            $fieldType = $fieldData['field_type'] ?? 'text';

            $formFieldData = [
                'ticket_type_id' => $ticketType->id,
                'field_name' => $fieldName,
                'field_type' => $fieldType,
                'field_label' => $fieldData['field_label'] ?? '',
                'required' => $fieldData['required'] ?? false,
                'description' => $fieldData['description'] ?? '',
                'placeholder' => $fieldData['placeholder'] ?? '',
                'options' => $fieldData['options'] ?? null,
                'hardware_limit' => $fieldData['hardware_limit'] ?? null,
                'include_no_type_hardware' => $fieldData['include_no_type_hardware'] ?? 0,
                'order' => $index + 1,
            ];

            // Se il tipo di campo è hardware, aggiungi il campo hardware_accessory_include
            if ($fieldType === 'hardware') {
                $formFieldData['hardware_accessory_include'] = $fieldData['hardware_accessory_include'] ?? 'both';
            }

            TypeFormFields::create($formFieldData);
        }
    }

    /**
     * Collega i ticket slave a un ticket master
     */
    protected function attachSlaveTicketTypes(TicketType $masterTicket, array $slaveTickets, Company $company): void
    {
        $slaveData = [];

        foreach ($slaveTickets as $slaveInfo) {
            // Trova il ticket type slave per questa azienda
            $slaveTicket = TicketType::where('name', $slaveInfo['type_name'])
                ->where('company_id', $company->id)
                ->first();

            if ($slaveTicket) {
                $slaveData[$slaveTicket->id] = [
                    'is_required' => $slaveInfo['required'] ?? false,
                ];
            }
        }

        if (! empty($slaveData)) {
            $masterTicket->slaveTypes()->sync($slaveData);
        }
    }
}
