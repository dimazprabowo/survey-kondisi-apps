<?php

use App\Http\Controllers\Api\SsoSyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// OnlyOffice Document Server — callback save/forcesave.
// Diverifikasi via JWT (secret sama dengan konfigurasi Document Server),
// bukan session auth. Selalu jawab {"error":0} agar DS tidak retry.
Route::post('/onlyoffice/report/{report}/callback', function (Request $request, \App\Models\SurveyReport $report) {
    $service = app(\App\Services\OnlyOfficeService::class);

    try {
        $payload = $service->callbackPayload($request);
    } catch (\Throwable $e) {
        return response()->json(['error' => 1, 'message' => 'Token tidak valid.'], 403);
    }

    try {
        $service->handleCallback($report, $payload);
    } catch (\Throwable $e) {
        report($e);

        return response()->json(['error' => 1, 'message' => 'Gagal menyimpan dokumen.'], 200);
    }

    return response()->json(['error' => 0]);
})->middleware('throttle:60,1')->name('onlyoffice.report.callback');

// SSO Server sync endpoints — only register if SSO is enabled
// Protected by X-SSO-Secret header + rate limiting
if (config('services.sso.enabled')) {
    Route::middleware(['throttle:60,1', 'sso.secret'])->prefix('sso')->group(function () {
        Route::get('/ping', [SsoSyncController::class, 'ping']);

        // User sync
        Route::post('/users/sync', [SsoSyncController::class, 'syncUser']);
        Route::post('/users/remove', [SsoSyncController::class, 'removeUser']);
        Route::post('/users/sync-roles', [SsoSyncController::class, 'syncUserRoles']);
        Route::get('/users', [SsoSyncController::class, 'listUsers']);

        // Role & Permission sync
        Route::post('/roles/sync', [SsoSyncController::class, 'syncRole']);
        Route::post('/roles/delete', [SsoSyncController::class, 'deleteRole']);
        Route::get('/roles', [SsoSyncController::class, 'listRoles']);
        Route::get('/permissions', [SsoSyncController::class, 'listPermissions']);
    });
}
