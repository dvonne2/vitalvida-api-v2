<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Monitoring Routes
|--------------------------------------------------------------------------
|
| These routes are for system monitoring and health checks.
| They are public and don't require authentication.
|
*/

Route::group(['prefix' => 'api/monitoring', 'middleware' => ['cors', 'security.headers']], function () {
    Route::get('/health', [App\Http\Controllers\Api\MonitoringController::class, 'health']);
    Route::get('/performance', [App\Http\Controllers\Api\MonitoringController::class, 'performance']);
    Route::get('/api-metrics', [App\Http\Controllers\Api\MonitoringController::class, 'apiMetrics']);
    Route::get('/database-metrics', [App\Http\Controllers\Api\MonitoringController::class, 'databaseMetrics']);
    Route::get('/cache-metrics', [App\Http\Controllers\Api\MonitoringController::class, 'cacheMetrics']);
    Route::get('/alerts', [App\Http\Controllers\Api\MonitoringController::class, 'alerts']);
    Route::get('/real-time-status', [App\Http\Controllers\Api\MonitoringController::class, 'realTimeStatus']);
}); 