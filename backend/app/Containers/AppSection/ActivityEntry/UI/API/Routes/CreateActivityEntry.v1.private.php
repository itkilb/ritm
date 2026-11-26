<?php

use App\Containers\AppSection\ActivityEntry\UI\API\Controllers\CreateActivityEntryController;
use Illuminate\Support\Facades\Route;

Route::post('activity-entries', CreateActivityEntryController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
