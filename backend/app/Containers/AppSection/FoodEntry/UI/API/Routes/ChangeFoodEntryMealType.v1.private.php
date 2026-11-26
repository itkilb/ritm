<?php

use App\Containers\AppSection\FoodEntry\UI\API\Controllers\ChangeFoodEntryMealTypeController;
use Illuminate\Support\Facades\Route;

Route::patch('food-entries/{id}/meal-type', ChangeFoodEntryMealTypeController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
