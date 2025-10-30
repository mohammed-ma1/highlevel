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

// Payment query endpoint for GoHighLevel (moved from web routes to avoid CSRF)
Route::post('/payment/query', [\App\Http\Controllers\PaymentQueryController::class, 'handleQuery'])
    ->name('api.payment.query');
