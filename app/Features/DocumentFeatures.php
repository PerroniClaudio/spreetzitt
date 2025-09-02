<?php

namespace App\Features;

class DocumentFeatures {
    /**
     * Define all available document features.
     */
    public static function getFeatures(): array {
        return [
            'list',
            'upload',
            'download',
            'delete',
            'search',
        ];
    }

    public function __invoke(string $feature) {
        return match ($feature) {
            'list' => $this->canListDocuments(),
            'upload' => $this->canUploadDocuments(),
            'download' => $this->canDownloadDocuments(),
            'delete' => $this->canDeleteDocuments(),
            'search' => $this->canSearchDocuments(),
            default => false,
        };
    }

    /**
     * Determine if the user can list documents.
     */
    private function canListDocuments(): bool {
        return $this->isTenantAllowed();
    }

    /**
     * Determine if the user can upload documents.
     */
    private function canUploadDocuments(): bool {
        return $this->isTenantAllowed();
    }

    /**
     * Determine if the user can download documents.
     */
    private function canDownloadDocuments(): bool {
        return $this->isTenantAllowed();
    }

    /**
     * Determine if the user can delete documents.
     */
    private function canDeleteDocuments(): bool {
        return $this->isTenantAllowed();
    }

    /**
     * Determine if the user can search documents.
     */
    private function canSearchDocuments(): bool {
        return $this->isTenantAllowed();
    }

    /**
     * Determine if the current tenant is allowed to use document features.
     */
    private function isTenantAllowed(): bool {
        $current_tenant = config('app.tenant');
        $allowedTenants = config('features-tenants.documents.allowed_tenants', ['domustart']);
        $excludedTenants = config('features-tenants.documents.excluded_tenants', ['spreetzit']);
        
        // Check if the tenant is explicitly excluded
        if (in_array($current_tenant, $excludedTenants, true)) {
            return false;
        }
        
        // Check if the tenant is explicitly allowed
        return in_array($current_tenant, $allowedTenants, true);
    }
}