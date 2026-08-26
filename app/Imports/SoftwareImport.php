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
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SoftwareImport implements ToCollection, WithMultipleSheets
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
    // 12 "Note",
    // 13 "ID Azienda",
    // 14 "ID Tipo software",
    // 15 "ID utenti (separati da virgola)",
    // 16 "ID utente responsabile dell'assegnazione (deve essere admin o del supporto)"

    protected $authUser;

    public function __construct($authUser)
    {
        $this->authUser = $authUser;
    }

    /**
     * @return array<int, self>
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            $statuses = config('app.software_statuses');
            $normalizedStatuses = array_map('strtolower', $statuses);

            foreach ($rows as $row) {
                if ($this->isEmptyRow($row)) {
                    continue;
                }

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
                if (! empty($row[4])) {
                    $isPresent = Software::where('company_asset_number', $row[4])->first();
                    if ($isPresent) {
                        throw new \Exception('Software con cespite aziendale '.$row[4].' già presente. ID: '.$isPresent->id);
                    }
                }

                // Verifica tipo software
                $companyId = $this->extractId($row[13] ?? null);
                $softwareTypeId = $this->extractId($row[14] ?? null);

                if (! empty($row[14])) {
                    $softwareType = SoftwareType::find($softwareTypeId);
                    if (! $softwareType) {
                        throw new \Exception('Tipo software non trovato per il software '.$row[1]);
                    }
                }

                // Verifica azienda
                if (! empty($row[13])) {
                    $isCompanyPresent = Company::find($companyId);
                    if (! $isCompanyPresent) {
                        throw new \Exception('ID Azienda errato per il software '.$row[1]);
                    }
                }

                if (! $this->canManageCompany($companyId)) {
                    throw new \Exception('Non puoi importare software per l\'azienda indicata.');
                }

                // Gestione date
                $purchaseDate = null;
                if (! empty($row[7])) {
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
                if (! empty($row[8])) {
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
                if (! empty($row[9])) {
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
                $licenseType = ! empty($row[5]) ? trim($row[5]) : null;

                // Stato (default: active)
                // $status = !empty($row[11]) ? trim($row[11]) : 'active';
                // Stato
                $inputStatus = strtolower(trim($row[11] ?? ''));
                $statusKey = array_search($inputStatus, $normalizedStatuses);
                if ($statusKey === false) {
                    $statusKey = 'active'; // fallback
                }

                $software = Software::create([
                    'vendor' => $row[0],
                    'product_name' => $row[1],
                    'version' => $row[2] ?? null,
                    'activation_key' => $row[3] ?? null,
                    'company_asset_number' => $row[4] ?? null,
                    'license_type' => $licenseType,
                    'max_installations' => ! empty($row[6]) && is_numeric($row[6]) ? (int) $row[6] : null,
                    'purchase_date' => $purchaseDate,
                    'expiration_date' => $expirationDate,
                    'support_expiration_date' => $supportExpirationDate,
                    'is_exclusive_use' => strtolower($row[10]) == 'si' ? 1 : 0,
                    'status' => $statusKey,
                    'notes' => $row[12] ?? null,
                    'company_id' => $companyId,
                    'software_type_id' => $softwareTypeId,
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

                $responsibleUserId = $this->extractId($row[16] ?? null);
                $responsibleUser = User::find($responsibleUserId);
                if ($responsibleUserId !== null && ! $this->canBeResponsible($responsibleUser, $software->company_id)) {
                    throw new \Exception('L\'utente con ID '.$responsibleUserId.' non può essere impostato come responsabile per l\'azienda indicata.');
                }

                if ($row[15] != null) {
                    if ($companyId === null) {
                        throw new \Exception('ID Azienda mancante per il software '.$row[1]);
                    }
                    $userIds = array_map(fn ($value) => $this->extractId($value), explode(',', $row[15]));
                    $usersCount = count($userIds);
                    $isCorrect = User::whereIn('id', $userIds)
                        ->whereHas('companies', fn ($query) => $query->where('companies.id', $companyId))
                        ->when($this->authUser->is_company_admin, fn ($query) => $query
                            ->where('is_admin', false)
                            ->where('is_superadmin', false))
                        ->count() == $usersCount;
                    if (! $isCorrect) {
                        throw new \Exception('ID utenti errati per il software '.$row[1]);
                    }
                    $users = $userIds;
                    if ($software->is_exclusive_use && count($users) > 1) {
                        throw new \Exception('Uso esclusivo impostato ma ci sono più utenti per il software '.$row[1]);
                    }
                    if (! $responsibleUser) {
                        $responsibleUser = User::find($this->authUser->id);
                    }
                    foreach ($users as $user) {
                        $software->users()->attach($user, [
                            'created_by' => $this->authUser->id ?? null,
                            'responsible_user_id' => $responsibleUser->id ?? $this->authUser->id ?? null,
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

    private function extractId(mixed $value): ?int
    {
        if (is_numeric($value) && (float) $value === floor((float) $value)) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^\s*(\d+)\s*-/', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function canBeResponsible(?User $user, ?int $companyId): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->authUser->is_company_admin) {
            return $companyId !== null
                && $user->is_company_admin
                && ! $user->is_admin
                && ! $user->is_superadmin
                && $user->hasCompany($companyId);
        }

        return $user->is_admin
            || $user->is_superadmin
            || ($companyId !== null && $user->is_company_admin && $user->hasCompany($companyId));
    }

    private function canManageCompany(?int $companyId): bool
    {
        if ($this->authUser->is_admin) {
            return true;
        }

        return $companyId !== null
            && $this->authUser->is_company_admin
            && $this->authUser->selectedCompany()?->id === $companyId;
    }

    /**
     * @param  Collection<int, mixed>  $row
     */
    private function isEmptyRow(Collection $row): bool
    {
        return $row->every(fn (mixed $value): bool => $value === null || trim((string) $value) === '');
    }
}
