<?php

use App\Containers\AppSection\UserProfile\UI\API\Controllers\UpdateMyProfileController;
use Illuminate\Support\Facades\Route;

Route::put('me/profile', UpdateMyProfileController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
