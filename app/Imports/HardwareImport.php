<?php

namespace App\Imports;

use App\Models\Company;
use App\Models\Hardware;
use App\Models\HardwareAuditLog;
use App\Models\HardwareType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;

class HardwareImport implements ToCollection
{
    // TEMPLATE IMPORT (INDICI):
    // 0 "Marca *",
    // 1 "Modello *",
    // 2 "Seriale (* se non è un accessorio)",
    // 3 "Tipo (testo, preso dalla lista nel gestionale)",
    // 4 "Data d'acquisto (gg/mm/aaaa)",
    // 5 "Proprietà (testo, preso tra le opzioni nel gestionale)",
    // 6 "Specificare (se proprietà è Altro)",
    // 7 "Cespite aziendale (se non è un accessorio, compilare almeno uno tra cespite aziendale e identificativo)",
    // 8 "Identificativo (se non è un accessorio, compilare almeno uno tra cespite aziendale e identificativo)",
    // 9 "Note",
    // 10 "Uso esclusivo (Si/No, Se manca viene impostato su No)",
    // 11 "ID Azienda",
    // 12 "ID utenti (separati da virgola)",
    // 13 "ID utente responsabile dell'assegnazione (deve essere admin o del supporto)",
    // 14 "Posizione (testo, preso tra le opzioni nel gestionale, Se manca viene impostato su 'Azienda')",
    // 15 "Stato all'acquisto (testo, preso tra le opzioni nel gestionale, Se manca viene impostato su 'Nuovo')",
    // 16 "Stato (testo, preso tra le opzioni nel gestionale, Se manca viene impostato su 'Nuovo')",
    // 17 'È un accessorio (Si/No, Se manca viene impostato su No)'

    protected $authUser;

    public function __construct($authUser)
    {
        $this->authUser = $authUser;
    }

    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            $positions = config('app.hardware_positions');
            $normalizedPositions = array_map('strtolower', $positions);
            $statuses = config('app.hardware_statuses');
            $normalizedStatuses = array_map('strtolower', $statuses);

            foreach ($rows as $row) {
                // Deve saltare la prima riga contentente i titoli (controlla Marca o Seriale)
                if ((isset($row[0]) && strpos(strtolower($row[0]), 'marca') !== false) || (isset($row[2]) && strpos(strtolower($row[2]), 'seriale') !== false)) {
                    continue;
                }

                if (empty($row[0])) {
                    throw new \Exception('Il campo marca è vuoto in una delle righe.');
                }
                if (empty($row[1])) {
                    throw new \Exception('Il campo modello è vuoto in una delle righe.');
                }

                // serial_number può essere vuoto per gli accessori
                $serial = isset($row[2]) ? trim($row[2]) : null;
                $companyAsset = isset($row[7]) ? trim($row[7]) : null;
                $supportLabel = isset($row[8]) ? trim($row[8]) : null;

                // default: accessory flag not provided -> default to false (non-accessory)
                $isAccessory = false;

                // If the template provides an explicit accessory flag (last column), parse Si/No (case-insensitive)
                $accessoryField = isset($row[17]) ? trim($row[17]) : null;
                if ($accessoryField !== null && $accessoryField !== '') {
                    $lower = mb_strtolower($accessoryField);
                    if (in_array($lower, ['si', 's', 'yes', 'y'])) {
                        $isAccessory = true;
                    } elseif (in_array($lower, ['no', 'n'])) {
                        $isAccessory = false;
                    }
                }

                if(!$isAccessory) {
                    if(empty($serial)){
                        throw new \Exception('Deve essere specificato il seriale per l\'hardware non accessorio nella riga con marca '.$row[0].' e modello '.$row[1].'.');
                    };
                    if(empty($companyAsset) && empty($supportLabel)){
                        throw new \Exception('Deve essere specificato il cespite aziendale o l\'identificativo per l\'hardware non accessorio nella riga con seriale '.($serial ?? '[vuoto]').'.');
                    };
                }

                $isPresent = null;
                if (! empty($serial)) {
                    $isPresent = Hardware::where('serial_number', $serial)->first();
                    if ($isPresent) {
                        throw new \Exception('Hardware con seriale '.$serial.' già presente. ID: '.$isPresent->id);
                    }
                } else {
                    // Se seriale vuoto, proviamo a fare matching solo se company_asset_number o support_label sono presenti
                    if (! empty($companyAsset) || ! empty($supportLabel)) {
                        $query = Hardware::query();
                        if (! empty($companyAsset)) {
                            $query->where('company_asset_number', $companyAsset);
                        }
                        if (! empty($supportLabel)) {
                            $query->orWhere('support_label', $supportLabel);
                        }
                        $isPresent = $query->first();
                    }
                }

                if (! empty($row[3])) {
                    $hardwareType = HardwareType::whereRaw('LOWER(name) = ?', [strtolower($row[3])])->first();
                    if (! $hardwareType) {
                        throw new \Exception('Tipo hardware non trovato per la riga con seriale '.($serial ?? '[vuoto]').'');
                    }
                }

                if (! empty($row[11])) {
                    $isCompanyPresent = Company::find($row[11]);
                    if (! $isCompanyPresent) {
                        throw new \Exception('ID Azienda errato per la riga con seriale '.($serial ?? '[vuoto]').'');
                    }
                }

                // 'hardware_ownership_types' => [
                //     "owned" => "Proprietà",
                //     "rented" => "Noleggio",
                //     "other" => "Altro",
                // ],
                $hardwareOwnershipTypes = config('app.hardware_ownership_types');
                $lowerOwnershipTypes = array_map('strtolower', $hardwareOwnershipTypes);
                $ownershipType = array_search(strtolower($row[5]), $lowerOwnershipTypes);
                if (! empty($row[5])) {
                    if (! (in_array(strtolower($row[5]), $lowerOwnershipTypes))
                    ) {
                        throw new \Exception('1 - Tipo di proprietà non valido per la riga con seriale '.($serial ?? '[vuoto]').' valore: '.$row[5].' - Possibili valori: '.implode(', ', $lowerOwnershipTypes));
                    }
                    if (! $ownershipType
                    ) {
                        throw new \Exception('2 - Tipo di proprietà non valido per la riga con seriale '.($serial ?? '[vuoto]').'');
                    }
                }

                // Gestione della data di acquisto
                $purchaseDate = null;
                if (! empty($row[4])) {
                    try {
                        if (is_numeric($row[4])) {
                            // Converti il numero seriale di Excel in una data
                            $purchaseDate = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[4]));
                        } else {
                            $purchaseDate = Carbon::createFromFormat('d/m/Y', $row[4]);
                        }
                    } catch (\Exception $e) {
                        throw new \Exception('Formato data non valido per la riga con seriale '.($serial ?? '[vuoto]').'. Valore: '.$row[4]);
                    }
                }

                // Posizione
                $inputPosition = strtolower(trim($row[14] ?? ''));
                $positionKey = array_search($inputPosition, $normalizedPositions);
                if ($positionKey === false) {
                    $positionKey = 'company'; // fallback
                }

                // Stato all'acquisto
                $inputStatusAtPurchase = strtolower(trim($row[15] ?? ''));
                $statusAtPurchaseKey = array_search($inputStatusAtPurchase, $normalizedStatusesAtPurchase = array_map('strtolower', config('app.hardware_statuses_at_purchase')) );
                if ($statusAtPurchaseKey === false) {
                    $statusAtPurchaseKey = 'new'; // fallback
                }

                // Stato (attuale)
                $inputStatus = strtolower(trim($row[16] ?? ''));
                $statusKey = array_search($inputStatus, $normalizedStatuses);
                if ($statusKey === false) {
                    $statusKey = 'new'; // fallback
                }

                // Se troviamo già un record via matching (seriale, o company_asset/support_label), interrompiamo l'import per evitare aggiornamenti
                if ($isPresent) {
                    throw new \Exception('Record già presente (matching) per la riga con seriale '.($serial ?? '[vuoto]').'. ID esistente: '.$isPresent->id);
                } else {
                    // Il controllo che ci sia almeno uno tra cespite aziendale e identificativo è fatto nel boot del modello, nel metodo creating.
                    $hardware = Hardware::create([
                        'make' => $row[0],
                        'model' => $row[1],
                        'serial_number' => $serial,
                        'is_accessory' => $isAccessory ? 1 : 0,
                        'hardware_type_id' => $hardwareType->id ?? null,
                        'purchase_date' => $purchaseDate,
                        'ownership_type' => $ownershipType ?? null,
                        'ownership_type_note' => $row[6] ?? null,
                        'company_asset_number' => $companyAsset ?? null,
                        'support_label' => $supportLabel ?? null,
                        'notes' => $row[9] ?? null,
                        'is_exclusive_use' => strtolower($row[10]) == 'si' ? 1 : 0,
                        'company_id' => $row[11] ?? null,
                        'status_at_purchase' => $statusAtPurchaseKey ?? null,
                        'status' => $statusKey,
                        'position' => $positionKey,
                    ]);
                }

                if (isset($hardware->company_id)) {
                    HardwareAuditLog::create([
                        'modified_by' => $this->authUser->id,
                        'hardware_id' => $hardware->id,
                        'log_subject' => 'hardware_company',
                        'log_type' => 'create',
                        'new_data' => json_encode(['company_id' => $hardware->company_id]),
                    ]);
                }

                if ($row[12] != null) {
                    if ($row[11] == null) {
                        throw new \Exception('ID Azienda mancante per l\'hardware con seriale '.$row[2]);
                    }
                    $userIds = explode(',', $row[12]);
                    $usersCount = count($userIds);
                    $isCorrect = User::whereIn('id', $userIds)
                        ->get()
                        ->filter(function ($user) use ($row) {
                            return $user->hasCompany($row[11]);
                        })
                        ->count() == $usersCount;
                    if (! $isCorrect) {
                        throw new \Exception('ID utenti errati per l\'hardware con seriale '.$row[2]);
                    }
                    $users = explode(',', $row[12]);
                    if ($hardware->is_exclusive_use && count($users) > 1) {
                        throw new \Exception('Uso esclusivo impostato ma ci sono più utenti per l\'hardware con seriale '.$row[2]);
                    }
                    $responsibleUser = User::find($row[13]);
                    if (! $responsibleUser) {
                        $responsibleUser = User::find($this->authUser->id);
                    }
                    // Non usiamo il sync perchè non eseguirebbe la funzione di boot del modello personalizzato HardwareUser
                    foreach ($users as $user) {
                        $hardware->users()->attach($user, ['created_by' => $this->authUser->id ?? null, 'responsible_user_id' => $responsibleUser->id ?? $this->authUser->id ?? null]);
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore durante l\'importazione dell\'hardware: '.$e->getMessage());
            throw $e;
        }
    }
}
