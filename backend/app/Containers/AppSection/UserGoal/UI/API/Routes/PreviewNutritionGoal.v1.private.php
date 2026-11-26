<?php

use App\Containers\AppSection\UserGoal\UI\API\Controllers\PreviewNutritionGoalController;
use Illuminate\Support\Facades\Route;

Route::post('goals/preview', PreviewNutritionGoalController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
