<?php

use App\Http\Controllers\MpesaCallbackController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Middleware\VerifyMpesaIpAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/mpesa/callback', [MpesaCallbackController::class, 'handleCallback'])
    ->middleware(VerifyMpesaIpAddress::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
});
