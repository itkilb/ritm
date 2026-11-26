<?php

use App\Containers\AppSection\Exercise\UI\API\Controllers\CreateExerciseController;
use Illuminate\Support\Facades\Route;

Route::post('exercises', CreateExerciseController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
