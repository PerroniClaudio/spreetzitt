<?php

namespace App\Features;

use Laravel\Pennant\Feature;

class TicketFeatures
{
    /**
     * Definisce tutte le feature disponibili per i ticket
     */
    public static function getFeatures(): array
    {
        return [
            'list',
            'create',
            'massive_generation',
            'types',
            'billing',
            'search',
            'search_erp',
            'show_visibility_fields',
        ];
    }

    public function __invoke(string $feature)
    {
        return match ($feature) {
            'list' => $this->canListTickets(),
            'create' => $this->canCreateTicket(),
            'massive_generation' => $this->canMassiveGeneration(),
            'types' => $this->canTypes(),
            'billing' => $this->canBilling(),
            'search' => $this->canSearch(),
            'search_erp' => $this->canSearchErp(),
            'show_visibility_fields' => $this->canShowVisibilityFields(),
            default => false,
        };
    }

    private function canListTickets()
    {
        return true;
    }

    private function canCreateTicket()
    {
        return true;
    }

    private function canMassiveGeneration()
    {
        return true;
    }

    private function canTypes()
    {
        return true;
    }

    private function canBilling()
    {
        return true;
    }

    private function canSearch()
    {
        return true;
    }

    private function canSearchErp()
    {
        return config('app.tenant') === 'spreetzit';
    }

    private function canShowVisibilityFields()
    {
        return $this->isTenantAllowed() && $this->isExclusiveFeatureEnabled('show_visibility_fields');
    }

    private function isTenantAllowed(): bool
    {
        $current_tenant = config('app.tenant');
        $allowedTenants = config('features-tenants.tickets.allowed_tenants', []);

        return in_array($current_tenant, $allowedTenants, true);
    }

    private function isExclusiveFeatureEnabled(string $feature): bool
    {
        $current_tenant = config('app.tenant');
        $exclusiveFeatures = config('features-tenants.tickets.exclusive_features', []);

        if (isset($exclusiveFeatures[$feature])) {
            return in_array($current_tenant, $exclusiveFeatures[$feature], true);
        }

        return false;
    }
}
