<?php

namespace App\Features;

class HardwareFeatures
{
    /**
     * Define all available hardware features.
     */
    public static function getFeatures(): array
    {
        return [
            'list',
            'massive_generation',
            'assign_massive',
            'hardware_delete_massive',
        ];
    }

    public function __invoke(string $feature)
    {
        return match ($feature) {
            'list' => $this->canListHardware(),
            'massive_generation' => $this->canMassiveGeneration(),
            'assign_massive' => $this->canAssignMassive(),
            'hardware_delete_massive' => $this->canHardwareDeleteMassive(),
            default => false,
        };
    }

    private function canListHardware()
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

    private function canHardwareDeleteMassive()
    {
        return $this->isTenantAllowed(); // Replace with allowed tenants
    }

    private function isTenantAllowed(): bool
    {
        $current_tenant = config('app.tenant');
        $allowedTenants = config('features-tenants.hardware.allowed_tenants', []);

        return in_array($current_tenant, $allowedTenants, true);
    }
}
