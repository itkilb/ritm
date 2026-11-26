<?php

use App\Containers\AppSection\Dashboard\UI\API\Controllers\GetDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', GetDashboardController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
