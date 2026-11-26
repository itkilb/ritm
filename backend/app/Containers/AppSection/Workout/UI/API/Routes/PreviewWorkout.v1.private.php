<?php

use App\Containers\AppSection\Workout\UI\API\Controllers\PreviewWorkoutController;
use Illuminate\Support\Facades\Route;

Route::post('workouts/preview', PreviewWorkoutController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
