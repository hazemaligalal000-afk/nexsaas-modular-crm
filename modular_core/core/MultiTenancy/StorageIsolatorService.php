<?php

namespace ModularCore\Core\MultiTenancy;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Requirement 8: File Storage Isolation (Phase 2)
 */
class StorageIsolatorService
{
    /**
     * Requirement 8.1: Structured tenant directory path
     * /storage/tenant_{tenant_id}/{file_type}/{filename}
     */
    public function store(UploadedFile $file, string $type = 'leads'): string
    {
        $tenantId = app('current_tenant_id');
        
        # Requirement 8.2: Unique UUID filename (Prevent Collisions)
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = "tenant_{$tenantId}/{$type}/{$filename}";

        Storage::disk('local')->putFileAs($path, $file, $filename);

        return $path;
    }

    /**
     * Requirement 8.3 & 8.4: Verify requesting tenant matches the file's tenant_id
     */
    public function secureUrl(string $path): string
    {
        $tenantId = app('current_tenant_id');
        $expectedPrefix = "tenant_{$tenantId}/";

        if (strpos($path, $expectedPrefix) !== 0) {
            # Requirement 8.5: 403 Forbidden for cross-tenant access
            abort(403, "CRITICAL SECURITY ERROR: Access to file belonging to another tenant is denied.");
        }

        return Storage::url($path);
    }

    /**
     * Requirement 13.5: Clean up on tenant deletion
     */
    public function deleteTenantData(string $tenantId)
    {
        Storage::disk('local')->deleteDirectory("tenant_{$tenantId}");
    }
}
