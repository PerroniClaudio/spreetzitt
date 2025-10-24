<?php

namespace App\Features;

class PropertyFeatures
{
    /**
     * Define all available properties features.
     */
    public static function getFeatures(): array
    {
        return [
            'list',
            'massive_generation',
            'assign_massive',
            'property_delete_massive',
        ];
    }

    public function __invoke(string $feature)
    {
        return match ($feature) {
            'list' => $this->canListProperty(),
            'massive_generation' => $this->canMassiveGeneration(),
            'assign_massive' => $this->canAssignMassive(),
            'property_delete_massive' => $this->canPropertyDeleteMassive(),
            default => false,
        };
    }

    private function canListProperty()
    {
        return $this->isTenantAllowed(); // Replace with allowed tenants
    }

    private function canMassiveGeneration()
    {
        return $this->isTenantAllowed(); // Replace with allowed tenants
    }

    private function canAssignMassive()
    {
        return $this->isTenantAllowed(); // Replace with allowed tenants
    }

    private function canPropertyDeleteMassive()
    {
        return $this->isTenantAllowed(); // Replace with allowed tenants
    }

    private function isTenantAllowed(): bool
    {
        $current_tenant = config('app.tenant');
        $allowedTenants = config('features-tenants.properties.allowed_tenants', []);

        return in_array($current_tenant, $allowedTenants, true);
    }
}
