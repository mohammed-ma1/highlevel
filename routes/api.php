<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientIntegrationController;

// Test route to verify API routes are working
Route::get('/test', function () {
    return response()->json(['message' => 'API routes are working!']);
});

Route::post('/charge/create-tap', [ClientIntegrationController::class, 'createTapCharge'])
    ->name('api.charge.create.tap');

// Get last charge status for popup payment flow
Route::get('/charge/last-status', [ClientIntegrationController::class, 'getLastChargeStatus'])
    ->name('api.charge.last-status');

// Payment query endpoint for GoHighLevel (API route - no CSRF protection)
Route::post('/payment/query', [\App\Http\Controllers\PaymentQueryController::class, 'handleQuery'])
    ->name('api.payment.query');
