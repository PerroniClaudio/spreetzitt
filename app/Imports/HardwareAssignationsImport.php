<?php

namespace App\Imports;

use App\Models\Company;
use App\Models\Hardware;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class HardwareAssignationsImport implements ToCollection, WithMultipleSheets
{
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

        // "ID hardware *",
        // "ID azienda da associare",
        // "ID utente/i da associare (separati da virgola)",
        // "ID azienda da rimuovere",
        // "ID utente/i da rimuovere (separati da virgola)",
        // "ID responsabile dell'assegnazione (deve essere admin o del supporto). Se non indicato viene impostato l'ID di chi carica il file."

        try {

            foreach ($rows as $row) {
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                // Deve saltare la prima riga contentente i titoli
                if (strpos(strtolower($row[0]), 'hardware') !== false) {
                    continue;
                }

                if (empty($row[0])) {
                    throw new \Exception('Il campo ID hardware è vuoto in una delle righe.');
                }
                if (empty($row[1]) && empty($row[2]) && empty($row[3]) && empty($row[4])) {
                    throw new \Exception('Tutti i campi azienda e utenti sono vuoti in una delle righe.');
                }

                $hardwareId = $this->extractId($row[0]);
                $companyToAddId = $this->extractId($row[1] ?? null);
                $companyToRemoveId = $this->extractId($row[3] ?? null);
                $responsibleUserId = $this->extractId($row[5] ?? null);
                $hardware = Hardware::find($hardwareId);

                if (! $hardware) {
                    throw new \Exception('Hardware con ID '.$row[0].' inesistente.');
                }

                if ($this->authUser->is_company_admin && ! $this->canManageAsset($hardware, $companyToAddId, $companyToRemoveId)) {
                    throw new \Exception('Non puoi modificare le assegnazioni dell\'hardware indicato.');
                }

                // Per ogni colonna verificare che la modifica sia possibile (partire dalle rimozioni)

                // Essendo in una transaction le relazioni non si aggiornano subito, quindi si devono salvare i dati per poter fare le verifiche prima di creare nuove associazioni.
                $removedUsers = [];

                // utenti da rimuovere
                if (! empty($row[4])) {
                    $usersToRemove = array_map(fn ($value) => $this->extractId($value), explode(',', $row[4]));
                    foreach ($usersToRemove as $userToRemove) {
                        $user = User::find($userToRemove);
                        if ($this->authUser->is_company_admin && ! $this->canAssignUser($user, $hardware->company_id)) {
                            throw new \Exception('L\'utente con ID '.$userToRemove.' non può essere gestito dal company admin.');
                        }
                        if ($user && $hardware->users->contains($user->id)) {
                            $hardware->users()->detach($user->id);
                            if (! in_array($user->id, $removedUsers)) {
                                $removedUsers[] = $user->id;
                            }
                        }
                    }
                }

                // Modifica azienda. Per avere un log migliore nel caso di cambio azienda è meglio collegare l'eliminazione della vecchia azienda e l'assegnazione della nuova
                if ($companyToRemoveId !== null) {
                    // azienda da rimuovere
                    $CompanyToRemove = Company::find($companyToRemoveId);
                    if ($hardware->company_id != null && ! $CompanyToRemove) {
                        throw new \Exception('Azienda con ID '.$row[3].' inesistente.');
                    }
                    if ($hardware->company_id != null && ($hardware->company_id != $CompanyToRemove->id)) {
                        throw new \Exception('L\'hardware con ID '.$row[0].' non è associato all\'azienda con ID '.$row[3]);
                    }
                    if ($CompanyToRemove) {
                        // Toglie tutti gli utenti assegnati
                        $hardware->users()->each(function ($user) use ($hardware, $CompanyToRemove) {
                            if ($user->hasCompany($CompanyToRemove->id)) {
                                $hardware->users()->detach($user->id);
                                $removedUsers[] = $user->id;
                            }
                        });
                        // Controlla se va sostituita o solo eliminata
                        if ($companyToAddId !== null) {
                            $hardware->company_id = $companyToAddId;
                        } else {
                            $hardware->company_id = null;
                        }
                        $hardware->save();
                    }
                } elseif ($companyToAddId !== null) {
                    // azienda da aggiungere
                    if ($hardware->company_id) {
                        throw new \Exception('L\'hardware con ID '.$row[0].' è già associato ad un\'azienda.');
                    }

                    $CompanyToAdd = Company::find($companyToAddId);
                    if (! $CompanyToAdd) {
                        throw new \Exception('Azienda con ID '.$row[1].' inesistente.');
                    }
                    $hardware->company_id = $CompanyToAdd->id;
                    $hardware->save();
                }

                if ($responsibleUserId !== null && ! $this->canBeResponsible(User::find($responsibleUserId), $hardware->company_id)) {
                    throw new \Exception('L\'utente con ID '.$responsibleUserId.' non può essere impostato come responsabile in quanto non è autorizzato per l\'azienda indicata.');
                }

                // utenti da aggiungere
                if (! empty($row[2])) {
                    $usersToAdd = array_map(fn ($value) => $this->extractId($value), explode(',', $row[2]));
                    if (count($usersToAdd) > 0) {
                        $remainingUsersCount = $hardware->users->filter(function ($user) use ($removedUsers) {
                            return ! in_array($user->id, $removedUsers);
                        })->count();
                        if ($hardware->is_exclusive_use && (count($usersToAdd) > 1 || ($remainingUsersCount > 0))) {
                            if ($remainingUsersCount > 0) {
                                throw new \Exception('Uso esclusivo impostato e ci sono già utenti assegnati per l\'hardware con ID '.$row[0]);
                            }
                            if (count($usersToAdd) > 1) {
                                throw new \Exception('Uso esclusivo impostato ma ci sono più utenti per l\'hardware con ID '.$row[0]);
                            }
                        }
                        foreach ($usersToAdd as $userToAdd) {
                            $user = User::find($userToAdd);
                            if (! $this->canAssignUser($user, $hardware->company_id)) {
                                throw new \Exception('L\'utente con ID '.$userToAdd.' non è assegnato alla stessa azienda dell\'hardware con ID '.$row[0]);
                            }
                            if ($user && ! $hardware->users->contains($user->id)) {
                                // Non usiamo il sync perchè non eseguirebbe la funzione di boot del modello personalizzato HardwareUser
                                $hardware->users()->attach($user->id, ['created_by' => $this->authUser->id ?? null, 'responsible_user_id' => $responsibleUserId ?? $this->authUser->id ?? null]);
                            }
                        }
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

    private function canManageAsset(Hardware $hardware, ?int $companyToAddId, ?int $companyToRemoveId): bool
    {
        $companyId = $this->authUser->selectedCompany()?->id;

        return $companyId !== null
            && $hardware->company_id === $companyId
            && $companyToAddId === null
            && $companyToRemoveId === null;
    }

    private function canAssignUser(?User $user, ?int $companyId): bool
    {
        if (! $user || $companyId === null || ! $user->hasCompany($companyId)) {
            return false;
        }

        return ! $this->authUser->is_company_admin || (! $user->is_admin && ! $user->is_superadmin);
    }

    /**
     * @param  Collection<int, mixed>  $row
     */
    private function isEmptyRow(Collection $row): bool
    {
        return $row->every(fn (mixed $value): bool => $value === null || trim((string) $value) === '');
    }
}
