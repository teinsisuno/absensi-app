<?php

namespace App\Jobs;

use App\Services\TenantProvisioningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Provisioning tenant Absensi (async biar response webhook ke Central cepat).
 */
class ProvisionTenantJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public array $payload)
    {
    }

    public function handle(TenantProvisioningService $service): void
    {
        try {
            $service->provision($this->payload);
        } catch (\Throwable $e) {
            Log::error('ProvisionTenantJob gagal', [
                'payload' => $this->payload,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
