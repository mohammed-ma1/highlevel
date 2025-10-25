<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientIntegrationController;
use App\Http\Controllers\GHLIntegrationController;

// Test route to verify API routes are working
Route::get('/test', function () {
    return response()->json(['message' => 'API routes are working!']);
});

Route::post('/charge/create-tap', [ClientIntegrationController::class, 'createTapCharge'])
    ->name('api.charge.create.tap');

Route::post('/charge/verify', [GHLIntegrationController::class, 'verifyCharge'])
    ->name('api.charge.verify');
