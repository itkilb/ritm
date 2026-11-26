<?php

use App\Containers\AppSection\Workout\UI\API\Controllers\GetWorkoutDiaryController;
use Illuminate\Support\Facades\Route;

Route::get('workouts', GetWorkoutDiaryController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
