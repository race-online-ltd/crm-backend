<?php

use App\Http\Controllers\Settings\SettingController;
use Illuminate\Support\Facades\Route;

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
