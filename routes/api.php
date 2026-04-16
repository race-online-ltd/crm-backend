<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Settings\BusinessEntityController;
use App\Http\Controllers\GroupControllers\GroupController;
use App\Http\Controllers\Settings\SettingController;
use App\Http\Controllers\TeamControllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    Route::middleware('auth:api')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:api')->group(function (): void {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('profile/change-password', [ProfileController::class, 'changePassword']);

    Route::prefix('system')->controller(SettingController::class)->group(function (): void {
        Route::get('/roles', 'rolesIndex');
        Route::post('/roles', 'storeRole');
        Route::put('/roles/{role}', 'updateRole');
        Route::patch('/roles/{role}', 'updateRole');
        Route::delete('/roles/{role}', 'destroyRole');

        Route::get('/users', 'usersIndex');
        Route::post('/users', 'storeUser');
        Route::put('/users/{systemUser}', 'updateUser');
        Route::patch('/users/{systemUser}', 'updateUser');
        Route::delete('/users/{systemUser}', 'destroyUser');
    });

    Route::prefix('system')->group(function (): void {
        Route::get('/business-entities', [BusinessEntityController::class, 'index']);
        Route::post('/business-entities', [BusinessEntityController::class, 'store']);
        Route::put('/business-entities/{businessEntity}', [BusinessEntityController::class, 'update']);
        Route::patch('/business-entities/{businessEntity}', [BusinessEntityController::class, 'update']);
        Route::delete('/business-entities/{businessEntity}', [BusinessEntityController::class, 'destroy']);

        Route::get('/teams', [TeamController::class, 'index']);
        Route::post('/teams', [TeamController::class, 'store']);
        Route::put('/teams/{team}', [TeamController::class, 'update']);
        Route::patch('/teams/{team}', [TeamController::class, 'update']);
        Route::delete('/teams/{team}', [TeamController::class, 'destroy']);

        Route::get('/groups', [GroupController::class, 'index']);
        Route::post('/groups', [GroupController::class, 'store']);
        Route::put('/groups/{group}', [GroupController::class, 'update']);
        Route::patch('/groups/{group}', [GroupController::class, 'update']);
        Route::delete('/groups/{group}', [GroupController::class, 'destroy']);
    });
});
