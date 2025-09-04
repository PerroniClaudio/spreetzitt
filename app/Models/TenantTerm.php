<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant',
        'key',
        'value',
        'description',
        'category',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Get terms for a specific tenant
     */
    public static function getTermsForTenant(string $tenant): array
    {
        return self::where('tenant', $tenant)
            ->get()
            ->keyBy('key')
            ->map(function ($term) {
                return $term->value;
            })
            ->toArray();
    }

    /**
     * Get a specific term for a tenant
     */
    public static function getTermForTenant(string $tenant, string $key, $default = null)
    {
        $term = self::where('tenant', $tenant)
            ->where('key', $key)
            ->first();

        return $term ? $term->value : $default;
    }

    /**
     * Set a term for a tenant
     */
    public static function setTermForTenant(string $tenant, string $key, $value, ?string $description = null, ?string $category = null): self
    {
        return self::updateOrCreate(
            ['tenant' => $tenant, 'key' => $key],
            [
                'value' => $value,
                'description' => $description,
                'category' => $category,
            ]
        );
    }

    /**
     * Get a term for the current tenant from environment
     */
    public static function getCurrentTenantTerm(string $key, $default = null)
    {
        $tenant = config('app.tenant', env('TENANT', 'default'));

        return self::getTermForTenant($tenant, $key, $default);
    }
}
