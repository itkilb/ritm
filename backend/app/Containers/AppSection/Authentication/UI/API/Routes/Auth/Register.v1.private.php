<?php

use App\Containers\AppSection\Authentication\UI\API\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::post('auth/register', RegisterController::class)
    ->middleware(['stateful-api', 'throttle:auth-register']);
