<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuditController;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\InventoriesController;

// --- Auth (JWT) ---
Route::prefix('auth')->group(function () {
    Route::post('login',   [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
    Route::post('logout',  [AuthController::class, 'logout'])->middleware('auth:api');
    Route::get('me',       [AuthController::class, 'me'])->middleware('auth:api');
    Route::post('register', [AuthController::class, 'register'])
    ->middleware(['auth:api','role:ADMIN']);
});

// Obtener roles disponibles (solo autenticados)
Route::middleware(['auth:api'])->group(function () {
    Route::get('/roles', [AuthController::class, 'getRoles']);
});

// Gestión de usuarios (solo ADMIN)
Route::middleware(['auth:api','role:ADMIN'])->group(function () {
    Route::get('/users', [AuthController::class, 'getUsers']);
    Route::put('/users/{userId}', [AuthController::class, 'updateUser']);
    Route::delete('/users/{userId}', [AuthController::class, 'deleteUser']);
});

// Supervisores endpoints (ADMIN|SUPERVISOR|AUDITOR)
Route::middleware(['auth:api','role:ADMIN|SUPERVISOR|AUDITOR'])->group(function () {
    Route::get('/supervisors', [AuthController::class, 'getSupervisors']);
    Route::get('/supervisor/{supervisorId}', [AuthController::class, 'getSupervisor']);
    Route::get('/locals/{localId}/supervisors', [AuthController::class, 'getSupervisorsByLocal']);
});

// Listar locals de una company (ADMIN|SUPERVISOR|AUDITOR)
Route::middleware(['auth:api','role:ADMIN|SUPERVISOR|AUDITOR'])->group(function () {
    Route::get('/companies', [OrganizationController::class, 'listCompanies']);
    Route::get('/companies/{companyId}/locals', [OrganizationController::class, 'listLocals']);
});

// Crear company/local (solo ADMIN)
Route::middleware(['auth:api','role:ADMIN'])->group(function () {
    Route::post('/companies', [OrganizationController::class, 'createCompany']);
    Route::post('/companies/{companyId}/locals', [OrganizationController::class, 'createLocal']);
});

// --- Writes: requieren ADMIN o SUPERVISOR ---
Route::middleware(['auth:api','role:ADMIN|SUPERVISOR'])->group(function () {
    Route::post('/audits/{localId}/open', [AuditController::class, 'open']);
    Route::post('/audits/{localId}/categories', [AuditController::class, 'addCategory']);
    Route::post('/audits/categories/{categoryId}/items', [AuditController::class, 'addItems']);
    Route::put('/audits/{localId}/categories/{categoryId}', [AuditController::class, 'renameCategory']);
    Route::delete('/audits/categories/{categoryId}', [AuditController::class, 'removeCategory']);
    Route::put('/audits/{localId}/categories/{categoryId}/items/{itemId}', [AuditController::class, 'updateItem']);
    Route::delete('/audits/{localId}/categories/{categoryId}/items/{itemId}', [AuditController::class, 'removeItem']);
    Route::post('/audits/{auditId}/close', [AuditController::class, 'close']);
    
    // Import/Export de auditorías
    Route::post('/audits/{localId}/import', [AuditController::class, 'import']);
    Route::get('/audits/{localId}/export', [AuditController::class, 'export']);
});

// --- Reads: ADMIN | SUPERVISOR | AUDITOR ---
Route::middleware(['auth:api','role:ADMIN|SUPERVISOR|AUDITOR'])->group(function () {
    Route::get('/audits/enums', [AuditController::class, 'getEnums']);
    Route::get('/audits', [AuditController::class, 'index']);
    Route::get('/audits/{auditId}', [AuditController::class, 'showById']);
    Route::get('/audits/{localId}', [AuditController::class, 'show']);
    Route::get('/items', [AuditController::class, 'listItems']);
    Route::get('/categories/{categoryId}/items', [AuditController::class, 'getCategoryItems']);
    Route::get('/locals/{localId}/categories', [AuditController::class, 'getLocalCategories']);
});

// --- Inventories: Writes (ADMIN|SUPERVISOR) ---
Route::middleware(['auth:api','role:ADMIN|SUPERVISOR'])->group(function () {
    Route::post('/locals/{localId}/inventories-areas', [InventoriesController::class, 'createArea']);
    Route::put('/inventories-areas/{areaId}', [InventoriesController::class, 'updateArea']);
    Route::delete('/inventories-areas/{areaId}', [InventoriesController::class, 'deleteArea']);
    Route::post('/inventories-areas/{areaId}/inventories', [InventoriesController::class, 'addInventories']);
    Route::put('/inventories/{inventoryId}', [InventoriesController::class, 'updateInventory']);
    Route::delete('/inventories/{inventoryId}', [InventoriesController::class, 'deleteInventory']);
    
    // Import/Export de inventories
    Route::post('/inventories-areas/{areaId}/import', [InventoriesController::class, 'import']);
    Route::get('/inventories-areas/{areaId}/export', [InventoriesController::class, 'export']);
});

// --- Inventories: Reads (ADMIN|SUPERVISOR|AUDITOR) ---
Route::middleware(['auth:api','role:ADMIN|SUPERVISOR|AUDITOR'])->group(function () {
    Route::get('/inventories', [InventoriesController::class, 'index']);
    Route::get('/locals/{localId}/inventories-areas', [InventoriesController::class, 'listAreas']);
    Route::get('/inventories-areas/{areaId}/inventories', [InventoriesController::class, 'listInventories']);
});
