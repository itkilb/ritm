<?php

use App\Containers\AppSection\WeightEntry\UI\API\Controllers\ListWeightEntriesController;
use Illuminate\Support\Facades\Route;

Route::get('weight-entries', ListWeightEntriesController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
