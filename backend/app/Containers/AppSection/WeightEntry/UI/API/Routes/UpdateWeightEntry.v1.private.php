<?php

use App\Containers\AppSection\WeightEntry\UI\API\Controllers\UpdateWeightEntryController;
use Illuminate\Support\Facades\Route;

Route::put('weight-entries/{id}', UpdateWeightEntryController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
