<?php

use App\Containers\AppSection\Authentication\UI\API\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', LoginController::class)
    ->middleware(['stateful-api', 'throttle:auth-login']);
