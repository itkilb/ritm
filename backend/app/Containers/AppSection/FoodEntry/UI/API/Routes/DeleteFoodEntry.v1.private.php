<?php

use App\Containers\AppSection\FoodEntry\UI\API\Controllers\DeleteFoodEntryController;
use Illuminate\Support\Facades\Route;

Route::delete('food-entries/{id}', DeleteFoodEntryController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
