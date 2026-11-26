<?php

use App\Containers\AppSection\WeightEntry\UI\API\Controllers\DeleteWeightEntryController;
use Illuminate\Support\Facades\Route;

Route::delete('weight-entries/{id}', DeleteWeightEntryController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
