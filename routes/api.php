<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Settings\SettingController;
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
});
