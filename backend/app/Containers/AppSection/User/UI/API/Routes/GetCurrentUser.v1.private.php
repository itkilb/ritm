<?php

use App\Containers\AppSection\User\UI\API\Controllers\GetCurrentUserController;
use Illuminate\Support\Facades\Route;

Route::get('me', GetCurrentUserController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
