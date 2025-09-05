<?php

use Illuminate\Support\Facades\Route;

// Test route
Route::get('/test', function () {
    return response()->json(['message' => 'Routes are working!']);
});

// Moniepoint Webhook Route
Route::post('/webhooks/moniepoint', [App\Http\Controllers\MoniepointWebhookController::class, 'handleWebhook']);

// Delivery Agent Routes
Route::post('/delivery/{order}/submit-otp', [App\Http\Controllers\DeliveryController::class, 'submitOtp']);
Route::get('/delivery/{order}/info', [App\Http\Controllers\DeliveryController::class, 'getOrderForOtp']);
