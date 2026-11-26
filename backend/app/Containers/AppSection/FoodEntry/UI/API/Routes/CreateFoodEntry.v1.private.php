<?php

use App\Containers\AppSection\FoodEntry\UI\API\Controllers\CreateFoodEntryController;
use Illuminate\Support\Facades\Route;

Route::post('food-entries', CreateFoodEntryController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
