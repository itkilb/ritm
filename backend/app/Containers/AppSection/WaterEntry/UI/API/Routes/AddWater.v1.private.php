<?php

use App\Containers\AppSection\WaterEntry\UI\API\Controllers\AddWaterController;
use Illuminate\Support\Facades\Route;

Route::post('water-entries', AddWaterController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
