<?php

namespace App\Imports;

use App\Models\Software;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;

class SoftwareDeletionsImport implements ToCollection
{
    protected $authUser;

    public function __construct($authUser)
    {
        $this->authUser = $authUser;
    }

    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        // "ID software *",
        // "Tipo di eliminazione Soft/Definitiva/Recupero *",

        try {

            foreach ($rows as $row) {
                // Deve saltare la prima riga contentente i titoli
                if (strpos(strtolower($row[0]), 'software') !== false) {
                    continue;
                }

                if (empty($row[0])) {
                    throw new \Exception('Il campo "ID software" è vuoto in una delle righe.');
                }
                if (empty($row[1])) {
                    throw new \Exception('Il campo "Tipo di eliminazione" è vuoto in una delle righe.');
                }
                if (!in_array(strtolower($row[1]), ['soft', 'definitiva', 'recupero'])) {
                    throw new \Exception('Il valore nel campo "Tipo di eliminazione" non è conforme nella riga con ID software '.$row[0]);
                }

                $software = Software::withTrashed()->find($row[0]);
                if ($software) {
                    $deletionType = strtolower($row[1]);
                    switch ($deletionType) {
                        case 'soft':
                            if (!$software->trashed()) {
                                $software->delete();
                            }
                            break;
                        case 'definitiva':
                            $software->forceDelete();
                            break;
                        case 'recupero':
                            if ($software->trashed()) {
                                $software->restore();
                            }
                            break;
                        default:
                            throw new \Exception('Il valore nel campo "Tipo di eliminazione" non è conforme nella riga con ID software '.$row[0]);
                            break;
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore durante l\'importazione delle eliminazioni software: '.$e->getMessage());
            throw $e;
        }
    }
}
