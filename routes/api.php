<?php

use App\Api\v1\Http\Controllers\AuthenticationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthenticationController::class, 'login']);

        Route::middleware('auth:api')->group(function () {
            Route::get('me', [AuthenticationController::class, 'me']);
            Route::post('logout', [AuthenticationController::class, 'logout']);
        });

        Route::post('refresh', [AuthenticationController::class, 'refresh']);
    });
});
