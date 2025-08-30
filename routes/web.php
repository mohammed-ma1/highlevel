<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientIntegrationController;
use App\Http\Controllers\LeadConnectorPaymentController;


Route::get('/connect', [ClientIntegrationController::class, 'connect'])
    ->name('client.connect');

Route::get('/landing', function () {
    return view('welcome')->name('welcome');
});
Route::get('/webhook', [ClientIntegrationController::class, 'webhook'])
    ->name('client.webhook');

Route::post('/provider/connect-or-disconnect', [ClientIntegrationController::class, 'connectOrDisconnect'])
    ->name('provider.connect_or_disconnect');