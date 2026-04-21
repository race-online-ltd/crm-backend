<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\AreaController\AreaController;
use App\Http\Controllers\Clients\ClientsController;
use App\Http\Controllers\Settings\BackofficeController;
use App\Http\Controllers\Settings\BusinessEntityController;
use App\Http\Controllers\GroupControllers\GroupController;
use App\Http\Controllers\MappingController\MappingController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\KamProductMappingController;
use App\Http\Controllers\Settings\SettingController;
use App\Http\Controllers\Settings\SystemAccountConnectionController;
use App\Http\Controllers\TeamControllers\TeamController;
use App\Http\Controllers\EntityColumnMappingController;
use App\Http\Controllers\NavigationItemController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::prefix('system')->group(function (): void {

    Route::post('/permissions/sync', function () {

        Artisan::call('permissions:sync');

        return response()->json([
            'message' => 'Permissions synced successfully',
            'data' => true
        ]);

    })
    ->middleware(['auth:api', 'permission']);

});

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    Route::middleware('auth:api')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:api')->group(function (): void {


    Route::get('/navigation-items/active', [NavigationItemController::class, 'getActiveItems']);
    Route::get('/navigation-features/{navigation_id}', [NavigationItemController::class, 'getByNavigationId']);
    Route::post('/user-view-permissions', [NavigationItemController::class, 'store']);
    Route::get('/user-view-permissions', [NavigationItemController::class, 'show']);
    Route::put('/user-view-permissions', [NavigationItemController::class, 'update']);


    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('profile/change-password', [ProfileController::class, 'changePassword']);

    Route::prefix('areas')->controller(AreaController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::get('/divisions/{division}/districts', 'districts');
        Route::get('/districts/{district}/thanas', 'thanas');
        Route::post('/', 'store');
        Route::put('/{type}/{id}', 'update');
        Route::patch('/{type}/{id}', 'update');
        Route::delete('/{type}/{id}', 'destroy');
    });

    Route::prefix('clients')->controller(ClientsController::class)->group(function (): void {
        Route::get('/', 'index')->defaults('permission', 'clients.view')
        ->middleware('permission');
        Route::post('/', 'store') ->defaults('permission', 'clients.create')
        ->middleware('permission');
        Route::get('/{client}', 'show')->defaults('permission', 'clients.view')
        ->middleware('permission');
        Route::put('/{client}', 'update')->defaults('permission', 'clients.update')
        ->middleware('permission');
        Route::patch('/{client}', 'update')->defaults('permission', 'clients.update')
        ->middleware('permission');
        Route::delete('/{client}', 'destroy')->defaults('permission', 'clients.delete')
        ->middleware('permission');
    });

    Route::prefix('system')->controller(SettingController::class)->group(function (): void {
        Route::get('/roles', 'rolesIndex');
        Route::get('/access-control', 'accessControlIndex');
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

    Route::prefix('system')->controller(RoleController::class)->group(function (): void {
        Route::get('/roles/{role}/permissions', 'rolePermission');
        Route::post('/roles/{role}/update-permissions', 'updateRolePermissions');
    });

    Route::prefix('system')->group(function (): void {
        Route::get('/backoffice/options', [BackofficeController::class, 'options']);
        Route::get('/backoffice', [BackofficeController::class, 'index']);
        Route::post('/backoffice', [BackofficeController::class, 'store']);
        Route::put('/backoffice/{backoffice}', [BackofficeController::class, 'update']);
        Route::patch('/backoffice/{backoffice}', [BackofficeController::class, 'update']);
        Route::delete('/backoffice/{backoffice}', [BackofficeController::class, 'destroy']);

        Route::get('/business-entities', [BusinessEntityController::class, 'index']);
        Route::post('/business-entities', [BusinessEntityController::class, 'store']);
        Route::put('/business-entities/{businessEntity}', [BusinessEntityController::class, 'update']);
        Route::patch('/business-entities/{businessEntity}', [BusinessEntityController::class, 'update']);
        Route::delete('/business-entities/{businessEntity}', [BusinessEntityController::class, 'destroy']);

        Route::get('/kam-mappings/options', [KamProductMappingController::class, 'options']);
        Route::get('/business-entities/{businessEntity}/products', [KamProductMappingController::class, 'products']);
        Route::get('/kam-mappings', [KamProductMappingController::class, 'show']);
        Route::post('/kam-mappings', [KamProductMappingController::class, 'store']);

        Route::get('/external-systems', [SystemAccountConnectionController::class, 'externalSystemsIndex']);
        Route::get('/external-systems/{externalSystem}/users', [SystemAccountConnectionController::class, 'externalSystemUsers']);
        Route::get('/users/{systemUser}/external-account-connections', [SystemAccountConnectionController::class, 'showUserConnections']);
        Route::post('/users/{systemUser}/external-account-connections', [SystemAccountConnectionController::class, 'storeUserConnections']);

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

        Route::post('/user-mappings', [MappingController::class, 'store']);
    });

    Route::prefix('entity-column-mappings')->group(function () {

    Route::get('/get-navigation-items', [EntityColumnMappingController::class, 'getNavigationItems']);
    Route::get('/get-table-items', [EntityColumnMappingController::class, 'getTableItems']);
    Route::get('/get-column-items', [EntityColumnMappingController::class, 'getColumnItems']);


    Route::get('/', [EntityColumnMappingController::class, 'index']);
    Route::post('/', [EntityColumnMappingController::class, 'store']);
    Route::get('/{id}', [EntityColumnMappingController::class, 'show']);
    Route::put('/{id}', [EntityColumnMappingController::class, 'update']);
    Route::delete('/{id}', [EntityColumnMappingController::class, 'destroy']);


});
});
