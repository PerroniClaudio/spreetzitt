<?php
namespace App\Imports;

use App\Models\Company;
use App\Models\Hardware;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;

class HardwareDeletionsImport implements ToCollection
{

    protected $authUser;

    public function __construct($authUser)
    {
        $this->authUser = $authUser;
    }
    
    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        // "ID hardware *",
        // "Tipo di eliminazione Soft/Definitiva/Recupero *",

        try {

            foreach ($rows as $row) {
                // Deve saltare la prima riga contentente i titoli
                if (strpos(strtolower($row[0]), 'hardware') !== false) {
                    continue;
                }

                if (empty($row[0])) {
                    throw new \Exception('Il campo "ID hardware" è vuoto in una delle righe.');
                }
                if (empty($row[1])) {
                    throw new \Exception('Il campo "Tipo di eliminazione" è vuoto in una delle righe.');
                }
                if(!in_array(strtolower($row[1]), ['soft', 'definitiva', 'recupero'])) {
                    throw new \Exception('Il valore nel campo "Tipo di eliminazione" non è conforme nella riga con ID hardware ' . $row[0]);
                }

                $hardware = Hardware::withTrashed()->find($row[0]);
                if($hardware) {
                    $deletionType = strtolower($row[1]);
                    switch ($deletionType) {
                        case 'soft': 
                            if(!$hardware->trashed()) {
                                $hardware->delete();
                            }
                        break;
                        case 'definitiva':
                            $hardware->forceDelete();
                        break;
                        case 'recupero':
                            if($hardware->trashed()) {
                                $hardware->restore();
                            }
                        break;
                        default:
                            throw new \Exception('Il valore nel campo "Tipo di eliminazione" non è conforme nella riga con ID hardware ' . $row[0]);
                            break;
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore durante l\'importazione dell\'hardware: ' . $e->getMessage());
            throw $e;
        }
    }
}