<?php

use App\Containers\AppSection\Workout\UI\API\Controllers\CreateWorkoutController;
use Illuminate\Support\Facades\Route;

Route::post('workouts', CreateWorkoutController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
