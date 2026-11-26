<?php

use App\Containers\AppSection\Authentication\UI\API\Controllers\Auth\LogoutController;
use Illuminate\Support\Facades\Route;

Route::post('auth/logout', LogoutController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
