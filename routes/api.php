<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientIntegrationController;
use App\Http\Controllers\UPaymentsChargeController;
use App\Http\Controllers\UPaymentsStatusController;
use App\Http\Controllers\UPaymentsQueryController;

// Test route to verify API routes are working
Route::get('/test', function () {
    return response()->json(['message' => 'API routes are working!']);
});

Route::get('/merchant-id', [ClientIntegrationController::class, 'getMerchantId']);
Route::post('/charge/create-tap', [ClientIntegrationController::class, 'createTapCharge'])
    ->name('api.charge.create.tap');

Route::post('/charge/create-upayment', [UPaymentsChargeController::class, 'createCharge'])
    ->name('api.charge.create.upayment');

Route::post('/upayment/webhook', [UPaymentsChargeController::class, 'webhook'])
    ->name('api.upayment.webhook');

Route::get('/upayment/status', [UPaymentsStatusController::class, 'status'])
    ->name('api.upayment.status');

Route::post('/upayment/query', [UPaymentsQueryController::class, 'handleQuery'])
    ->name('api.upayment.query');

// Get last charge status for popup payment flow
Route::get('/charge/last-status', [ClientIntegrationController::class, 'getLastChargeStatus'])
    ->name('api.charge.last-status');

// Payment query endpoint for GoHighLevel (API route - no CSRF protection)
Route::post('/payment/query', [\App\Http\Controllers\PaymentQueryController::class, 'handleQuery'])
    ->name('api.payment.query');
