<?php

use App\Containers\AppSection\Workout\UI\API\Controllers\UpdateWorkoutController;
use Illuminate\Support\Facades\Route;

Route::put('workouts/{id}', UpdateWorkoutController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
