<?php

use App\Containers\AppSection\WeightEntry\UI\API\Controllers\UpdateTargetWeightController;
use Illuminate\Support\Facades\Route;

Route::put('me/target-weight', UpdateTargetWeightController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
