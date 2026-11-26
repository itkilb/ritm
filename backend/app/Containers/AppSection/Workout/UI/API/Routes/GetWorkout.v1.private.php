<?php

use App\Containers\AppSection\Workout\UI\API\Controllers\GetWorkoutController;
use Illuminate\Support\Facades\Route;

Route::get('workouts/{id}', GetWorkoutController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
