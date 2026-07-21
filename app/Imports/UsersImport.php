<?php

namespace App\Imports;

use App\Jobs\SendWelcomeEmail;
use App\Models\ActivationToken;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class UsersImport implements ToModel, WithMultipleSheets
{
    // TEMPLATE IMPORT:
    // "Nome",
    // "Cognome",
    // "Email",
    // "Abilitazione (UTENTE/AMMINISTRATORE)",
    // "ID Azienda"

    /**
     * @var array<int, int>
     */
    private array $allowedCompanyIds;

    public function __construct(User $authUser)
    {
        $this->allowedCompanyIds = $authUser->is_admin
            ? Company::query()->pluck('id')->all()
            : [$authUser->selectedCompany()?->id];
    }

    /**
     * @return array<int, self>
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        if ($this->isEmptyRow($row) || $this->isHeaderRow($row)) {
            return null;
        }

        $companyId = $this->extractId($row[4] ?? null);
        if ($companyId === null || ! in_array($companyId, $this->allowedCompanyIds, true)) {
            throw new \Exception('ID Azienda non autorizzato nel file di importazione.');
        }

        $isPresent = User::where('email', $row[2])->first();

        if ($isPresent) {
            return null;
        }

        $newUser = User::create([
            'name' => $row[0],
            'surname' => $row[1],
            'email' => $row[2],
            'is_company_admin' => strtolower($row[3]) == 'amministratore',
            // 'company_id' => $row[4],
            'phone' => $row[5] ?? null,
            'city' => $row[6] ?? null,
            'zip_code' => $row[7] ?? null,
            'address' => $row[8] ?? null,
            'password' => Hash::make(Str::password()),
        ]);

        if (Company::whereKey($companyId)->exists()) {
            $newUser->companies()->attach($companyId);
        }

        $activation_token = ActivationToken::create([
            // 'token' => Hash::make(Str::random(32)),
            'token' => Str::random(20).time(),
            'uid' => $newUser['id'],
            'status' => 0,
        ]);

        // Inviare mail con url: frontendBaseUrl + /support/set-password/ + activation_token['token]
        $url = env('FRONTEND_URL').'/support/set-password/'.$activation_token['token'];
        dispatch(new SendWelcomeEmail($newUser, $url));
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

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        return collect($row)->every(fn (mixed $value): bool => $value === null || trim((string) $value) === '');
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isHeaderRow(array $row): bool
    {
        return mb_strtolower(trim((string) ($row[2] ?? ''))) === 'email *';
    }
}
