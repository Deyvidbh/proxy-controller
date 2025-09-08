<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookDispatcherController;
use App\Http\Controllers\Squid\PortCheckController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/proxy/validate-port', [PortCheckController::class, 'validatePort']);

Route::post('/mp/webhook', [WebhookDispatcherController::class, 'mercadoPagoHandleWebhook']);