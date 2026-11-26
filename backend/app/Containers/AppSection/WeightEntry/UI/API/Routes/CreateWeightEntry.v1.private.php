<?php

use App\Containers\AppSection\WeightEntry\UI\API\Controllers\CreateWeightEntryController;
use Illuminate\Support\Facades\Route;

Route::post('weight-entries', CreateWeightEntryController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
