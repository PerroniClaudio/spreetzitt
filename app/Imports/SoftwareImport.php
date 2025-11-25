<?php

namespace App\Imports;

use App\Models\Company;
use App\Models\Software;
use App\Models\SoftwareAuditLog;
use App\Models\SoftwareType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;

class SoftwareImport implements ToCollection
{
    // TEMPLATE IMPORT:
    // 0 "Fornitore *",
    // 1 "Nome prodotto *",
    // 2 "Versione",
    // 3 "Chiave di attivazione",
    // 4 "Cespite aziendale (univoco)",
    // 5 "Tipo di licenza (perpetua, abbonamento, trial, open-source)",
    // 6 "Numero massimo installazioni",
    // 7 "Data d'acquisto (gg/mm/aaaa)",
    // 8 "Data scadenza (gg/mm/aaaa)",
    // 9 "Data scadenza supporto (gg/mm/aaaa)",
    // 10 "Uso esclusivo (Si/No, Se manca viene impostato su No)",
    // 11 "Stato (testo, preso tra le opzioni nel gestionale, Se manca viene impostato su 'active')",
    // 12 "ID Azienda",
    // 13 "ID Tipo software",
    // 14 "ID utenti (separati da virgola)",
    // 15 "ID utente responsabile dell'assegnazione (deve essere admin o del supporto)"

    protected $authUser;

    public function __construct($authUser)
    {
        $this->authUser = $authUser;
    }

    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                // Deve saltare la prima riga contentente i titoli
                if (strpos(strtolower($row[0]), 'fornitore') !== false) {
                    continue;
                }

                if (empty($row[0])) {
                    throw new \Exception('Il campo fornitore è vuoto in una delle righe.');
                }
                if (empty($row[1])) {
                    throw new \Exception('Il campo nome prodotto è vuoto in una delle righe.');
                }

                // Verifica unicità cespite aziendale se presente
                if (!empty($row[4])) {
                    $isPresent = Software::where('company_asset_number', $row[4])->first();
                    if ($isPresent) {
                        throw new \Exception('Software con cespite aziendale '.$row[4].' già presente. ID: '.$isPresent->id);
                    }
                }

                // Verifica tipo software
                if (!empty($row[13])) {
                    $softwareType = SoftwareType::find($row[13]);
                    if (!$softwareType) {
                        throw new \Exception('Tipo software non trovato per il software '.$row[1]);
                    }
                }

                // Verifica azienda
                if (!empty($row[12])) {
                    $isCompanyPresent = Company::find($row[12]);
                    if (!$isCompanyPresent) {
                        throw new \Exception('ID Azienda errato per il software '.$row[1]);
                    }
                }

                // Gestione date
                $purchaseDate = null;
                if (!empty($row[7])) {
                    try {
                        if (is_numeric($row[7])) {
                            $purchaseDate = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[7]));
                        } else {
                            $purchaseDate = Carbon::createFromFormat('d/m/Y', $row[7]);
                        }
                    } catch (\Exception $e) {
                        throw new \Exception('Formato data di acquisto non valido per il software '.$row[1].'. Valore: '.$row[7]);
                    }
                }

                $expirationDate = null;
                if (!empty($row[8])) {
                    try {
                        if (is_numeric($row[8])) {
                            $expirationDate = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[8]));
                        } else {
                            $expirationDate = Carbon::createFromFormat('d/m/Y', $row[8]);
                        }
                    } catch (\Exception $e) {
                        throw new \Exception('Formato data scadenza non valido per il software '.$row[1].'. Valore: '.$row[8]);
                    }
                }

                $supportExpirationDate = null;
                if (!empty($row[9])) {
                    try {
                        if (is_numeric($row[9])) {
                            $supportExpirationDate = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[9]));
                        } else {
                            $supportExpirationDate = Carbon::createFromFormat('d/m/Y', $row[9]);
                        }
                    } catch (\Exception $e) {
                        throw new \Exception('Formato data scadenza supporto non valido per il software '.$row[1].'. Valore: '.$row[9]);
                    }
                }

                // Tipo licenza - nessuna validazione, accetta qualsiasi valore
                $licenseType = !empty($row[5]) ? trim($row[5]) : null;

                // Stato (default: active)
                $status = !empty($row[11]) ? trim($row[11]) : 'active';

                $software = Software::create([
                    'vendor' => $row[0],
                    'product_name' => $row[1],
                    'version' => $row[2] ?? null,
                    'activation_key' => $row[3] ?? null,
                    'company_asset_number' => $row[4] ?? null,
                    'license_type' => $licenseType,
                    'max_installations' => !empty($row[6]) && is_numeric($row[6]) ? (int)$row[6] : null,
                    'purchase_date' => $purchaseDate,
                    'expiration_date' => $expirationDate,
                    'support_expiration_date' => $supportExpirationDate,
                    'is_exclusive_use' => strtolower($row[10]) == 'si' ? 1 : 0,
                    'status' => $status,
                    'company_id' => $row[12] ?? null,
                    'software_type_id' => $row[13] ?? null,
                ]);

                if (isset($software->company_id)) {
                    SoftwareAuditLog::create([
                        'modified_by' => $this->authUser->id,
                        'software_id' => $software->id,
                        'log_subject' => 'software_company',
                        'log_type' => 'create',
                        'new_data' => json_encode(['company_id' => $software->company_id]),
                    ]);
                }

                if ($row[14] != null) {
                    if ($row[12] == null) {
                        throw new \Exception('ID Azienda mancante per il software '.$row[1]);
                    }
                    $userIds = explode(',', $row[14]);
                    $usersCount = count($userIds);
                    $isCorrect = User::whereIn('id', $userIds)
                        ->get()
                        ->filter(function ($user) use ($row) {
                            return $user->hasCompany($row[12]);
                        })
                        ->count() == $usersCount;
                    if (!$isCorrect) {
                        throw new \Exception('ID utenti errati per il software '.$row[1]);
                    }
                    $users = explode(',', $row[14]);
                    if ($software->is_exclusive_use && count($users) > 1) {
                        throw new \Exception('Uso esclusivo impostato ma ci sono più utenti per il software '.$row[1]);
                    }
                    $responsibleUser = User::find($row[15]);
                    if (!$responsibleUser) {
                        $responsibleUser = User::find($this->authUser->id);
                    }
                    foreach ($users as $user) {
                        $software->users()->attach($user, [
                            'created_by' => $this->authUser->id ?? null,
                            'responsible_user_id' => $responsibleUser->id ?? $this->authUser->id ?? null
                        ]);
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore durante l\'importazione del software: '.$e->getMessage());
            throw $e;
        }
    }
}
