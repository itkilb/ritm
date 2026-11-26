<?php

use App\Containers\AppSection\WeeklyStats\UI\API\Controllers\GetWeeklyStatsController;
use Illuminate\Support\Facades\Route;

Route::get('weekly-stats', GetWeeklyStatsController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
