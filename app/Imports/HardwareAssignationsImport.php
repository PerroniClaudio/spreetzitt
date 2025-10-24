<?php

namespace App\Imports;

use App\Models\Company;
use App\Models\Hardware;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;

class HardwareAssignationsImport implements ToCollection
{
    protected $authUser;

    public function __construct($authUser)
    {
        $this->authUser = $authUser;
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

                $hardware = Hardware::find($row[0]);

                if (! $hardware) {
                    throw new \Exception('Hardware con ID '.$row[0].' inesistente.');
                }

                // Per ogni colonna verificare che la modifica sia possibile (partire dalle rimozioni)

                // Essendo in una transaction le relazioni non si aggiornano subito, quindi si devono salvare i dati per poter fare le verifiche prima di creare nuove associazioni.
                $removedUsers = [];

                // utenti da rimuovere
                if (! empty($row[4])) {
                    $usersToRemove = explode(',', $row[4]);
                    foreach ($usersToRemove as $userToRemove) {
                        $user = User::find($userToRemove);
                        if ($user && $hardware->users->contains($user->id)) {
                            $hardware->users()->detach($user->id);
                            if (! in_array($user->id, $removedUsers)) {
                                $removedUsers[] = $user->id;
                            }
                        }
                    }
                }

                // Modifica azienda. Per avere un log migliore nel caso di cambio azienda è meglio collegare l'eliminazione della vecchia azienda e l'assegnazione della nuova
                if (! empty($row[3])) {
                    // azienda da rimuovere
                    $CompanyToRemove = Company::find($row[3]);
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
                        if (! empty($row[1])) {
                            $hardware->company_id = $row[1];
                        } else {
                            $hardware->company_id = null;
                        }
                        $hardware->save();
                    }
                } elseif (! empty($row[1])) {
                    // azienda da aggiungere
                    if ($hardware->company_id) {
                        throw new \Exception('L\'hardware con ID '.$row[0].' è già associato ad un\'azienda.');
                    }

                    $CompanyToAdd = Company::find($row[1]);
                    if (! $CompanyToAdd) {
                        throw new \Exception('Azienda con ID '.$row[1].' inesistente.');
                    }
                    $hardware->company_id = $CompanyToAdd->id;
                    $hardware->save();
                }

                // utenti da aggiungere
                if (! empty($row[2])) {
                    $usersToAdd = explode(',', $row[2]);
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
                            if ($user && ! $user->hasCompany($hardware->company_id)) {
                                throw new \Exception('L\'utente con ID '.$userToAdd.' non è assegnato alla stessa azienda dell\'hardware con ID '.$row[0]);
                            }
                            if ($user && ! $hardware->users->contains($user->id)) {
                                // if($row[5] && !User::where(['id' => $row[5], 'company_id' => $hardware->company_id, 'is_company_admin' => true])
                                if ($row[5]) {
                                    $responsibleUser = User::find($row[5]);
                                    if (
                                        ! $responsibleUser ||
                                        (
                                            ! $responsibleUser->hasCompany($hardware->company_id) ||
                                            (! $responsibleUser->is_company_admin && ! $responsibleUser->is_admin)
                                        )
                                    ) {
                                        throw new \Exception('L\'utente con ID '.$row[5].' non può essere impostato come responsabile in quanto non è un amministratore dell\'azienda indicata o del supporto.');
                                    }
                                }
                                // Non usiamo il sync perchè non eseguirebbe la funzione di boot del modello personalizzato HardwareUser
                                $hardware->users()->attach($user->id, ['created_by' => $this->authUser->id ?? null, 'responsible_user_id' => $row[5] ?? $this->authUser->id ?? null]);
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
}
