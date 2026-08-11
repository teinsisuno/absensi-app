<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\ProvisionTenantJob;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook provisioning dari Central Platform.
 */
class ProvisioningController extends Controller
{
    public function tenant(Request $request): JsonResponse
    {
        // Validasi secret webhook
        $secret = config('absensi.webhook_secret');
        $header = $request->header('X-Absensi-Webhook-Secret');
        if ($secret === '' || ! hash_equals($secret, (string) $header)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $validated = $request->validate([
            'tenant_slug' => ['required', 'string', 'regex:/^[a-z0-9\-]+$/'],
            'tenant_name' => ['sometimes', 'string', 'max:255'],
            'owner_email' => ['sometimes', 'nullable', 'email'],
            'subscription_id' => ['sometimes', 'nullable', 'string'],
            'central_tenant_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        // Dispatch async (di dev QUEUE_CONNECTION=sync → jalan langsung)
        ProvisionTenantJob::dispatch($validated);

        return response()->json([
            'status' => 'queued',
            'message' => 'Provisioning tenant diterima.',
        ], 202);
    }
}
