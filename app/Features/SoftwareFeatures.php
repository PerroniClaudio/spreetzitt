<?php

namespace App\Features;

class SoftwareFeatures
{
    /**
     * Define all available software features.
     */
    public static function getFeatures(): array
    {
        return [
            'list',
            'massive_generation',
            'assign_massive',
            'software_delete_massive',
        ];
    }

    public function __invoke(string $feature)
    {
        return match ($feature) {
            'list' => $this->canListSoftware(),
            'massive_generation' => $this->canMassiveGeneration(),
            'assign_massive' => $this->canAssignMassive(),
            'software_delete_massive' => $this->canSoftwareDeleteMassive(),
            default => false,
        };
    }

    private function canListSoftware()
    {
        return $this->isTenantAllowed();
    }

    private function canMassiveGeneration()
    {
        return $this->isTenantAllowed();
    }

    private function canAssignMassive()
    {
        return $this->isTenantAllowed();
    }

    private function canSoftwareDeleteMassive()
    {
        return $this->isTenantAllowed();
    }

    private function isTenantAllowed(): bool
    {
        $current_tenant = config('app.tenant');
        $allowedTenants = config('features-tenants.software.allowed_tenants', []);

        return in_array($current_tenant, $allowedTenants, true);
    }
}
