<?php

use App\Containers\AppSection\Progress\UI\API\Controllers\GetProgressController;
use Illuminate\Support\Facades\Route;

Route::get('progress', GetProgressController::class)
    ->middleware(['stateful-api', 'auth:sanctum']);
