<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhook/endpoint', [WebhookController::class, 'handle']);
