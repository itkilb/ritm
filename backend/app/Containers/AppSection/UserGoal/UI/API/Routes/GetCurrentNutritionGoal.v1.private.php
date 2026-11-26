<?php

use App\Containers\AppSection\UserGoal\UI\API\Controllers\GetCurrentNutritionGoalController;
use Illuminate\Support\Facades\Route;

Route::get('goals/current', GetCurrentNutritionGoalController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
