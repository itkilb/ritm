<?php

use App\Containers\AppSection\Exercise\UI\API\Controllers\ListExercisesController;
use Illuminate\Support\Facades\Route;

Route::get('exercises', ListExercisesController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
