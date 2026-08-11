<?php

use App\Http\Controllers\Api\V1\ProvisioningController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Central Routes (tanpa tenancy — dipanggil server-to-server oleh Central Platform)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Webhook provisioning tenant dari Central (diproteksi secret header)
    Route::post('/provisioning/tenant', [ProvisioningController::class, 'tenant']);
});
