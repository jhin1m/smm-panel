<?php

use App\Http\Controllers\SepayWebhookController;
use App\Http\Middleware\VerifySepaySignature;
use Illuminate\Support\Facades\Route;

// Không thuộc nhóm web: không session, không CSRF, không ghi bảng sessions.
Route::post('/webhooks/sepay', SepayWebhookController::class)
    ->middleware(VerifySepaySignature::class)
    ->name('webhooks.sepay');
