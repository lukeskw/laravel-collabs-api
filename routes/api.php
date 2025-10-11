<?php

use App\Api\v1\Http\Actions\Collaborators\ImportCollaboratorsAction;
use App\Api\v1\Http\Controllers\AuthenticationController;
use App\Api\v1\Http\Controllers\CollaboratorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->as('api.v1.')
    ->group(function (): void {
        Route::prefix('auth')
            ->as('auth.')
            ->group(function (): void {
                Route::post('login', [AuthenticationController::class, 'login'])
                    ->name('login');
                Route::post('refresh', [AuthenticationController::class, 'refresh'])
                    ->name('refresh');
            });

        Route::middleware('auth:api')->group(function (): void {
            Route::get('auth/me', [AuthenticationController::class, 'me'])
                ->name('auth.me');
            Route::post('auth/logout', [AuthenticationController::class, 'logout'])
                ->name('auth.logout');

            Route::prefix('collaborators')
                ->as('collaborators.')
                ->group(function (): void {
                    Route::post('import', ImportCollaboratorsAction::class)
                        ->name('import');
                    Route::controller(CollaboratorController::class)->group(function (): void {
                        Route::get('', 'index')->name('index');
                        Route::post('', 'store')->name('store');
                        Route::get('{collaborator}', 'show')->name('show');
                        Route::match(['put', 'patch'], '{collaborator}', 'update')->name('update');
                        Route::delete('{collaborator}', 'destroy')->name('destroy');
                    });
                });
        });
    });
