<?php

namespace App\Imports;

use App\Jobs\SendWelcomeEmail;
use App\Models\ActivationToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;

class UsersImport implements ToModel
{
    // TEMPLATE IMPORT:
    // "Nome",
    // "Cognome",
    // "Email",
    // "Abilitazione (UTENTE/AMMINISTRATORE)",
    // "ID Azienda"
    
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        // Deve saltare la prima riga contentente i titoli
        if (strpos(strtolower($row[2]), 'email') !== false) {
            return null;
        }

        $isPresent = User::where('email', $row[2])->first();

        if ($isPresent) {
            return null;
        }

        $newUser = User::create([
            'name' => $row[0],
            'surname' => $row[1],
            'email' => $row[2],
            'is_company_admin' => strtolower($row[3]) == "amministratore",
            // 'company_id' => $row[4],
            'phone' => $row[5] ?? null,
            'city' => $row[6] ?? null,
            'zip_code' => $row[7] ?? null,
            'address' => $row[8] ?? null,
            'password' => Hash::make(Str::password()),
        ]);

        if(isset($row[4]) && Company::where('id', $row[4])->exists()) {
            $newUser->companies()->attach($row[4]);
        }

        $activation_token = ActivationToken::create([
            // 'token' => Hash::make(Str::random(32)),
            'token' => Str::random(20) . time(),
            'uid' => $newUser['id'],
            'status' => 0,
        ]);

        // Inviare mail con url: frontendBaseUrl + /support/set-password/ + activation_token['token]
        $url = env('FRONTEND_URL') . '/support/set-password/' . $activation_token['token'];
        dispatch(new SendWelcomeEmail($newUser, $url));
    }
}
