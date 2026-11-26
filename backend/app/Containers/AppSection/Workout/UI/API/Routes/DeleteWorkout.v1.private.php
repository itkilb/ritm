<?php

use App\Containers\AppSection\Workout\UI\API\Controllers\DeleteWorkoutController;
use Illuminate\Support\Facades\Route;

Route::delete('workouts/{id}', DeleteWorkoutController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
