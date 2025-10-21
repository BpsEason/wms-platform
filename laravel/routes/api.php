<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Modules\Inventory\Http\Controllers\InboundController;
use App\Modules\Inventory\Http\Controllers\OutboundController;
use App\Modules\Inventory\Http\Controllers\PickingController;
use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\System\Http\Controllers\UserController;
use App\Modules\System\Http\Controllers\LocationController;
use App\Modules\System\Http\Controllers\ProductController;

// =================================================================
// WMS API Routes (Consolidated ACL & Authentication)
// =================================================================

// --- 1. Authentication Module Routes (AuthController is used) ---
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    
    // Protected authentication routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// --- 2. Inventory Module Routes ---
// 權限要求：必須登入 (auth:sanctum)
Route::prefix('inventory')->middleware('auth:sanctum')->group(function () {
    // Inventory query (requires inventory-query permission)
    Route::get('/', [InventoryController::class, 'index'])->middleware('ability:inventory-query');
    
    // Inventory adjustment (requires inventory-adjust permission)
    Route::post('adjust', [InventoryController::class, 'adjust'])->middleware('ability:inventory-adjust');
});

// --- 3. Admin System Routes (System Management) ---
// 權限要求：必須登入 (auth:sanctum) 且 擁有 system-admin 權限 (ability)
Route::prefix('admin')->middleware(['auth:sanctum', 'ability:system-admin'])->group(function () {
    
    // User and permission management (UserController)
    Route::prefix('users')->group(function () {
        // CRUD for users
        Route::apiResource('', UserController::class)->except(['show']); 
        
        // Endpoint to sync user abilities and revoke tokens (Crucial for Step 5)
        Route::post('{user}/abilities', [UserController::class, 'syncAbilities']);
        
        // Get all available abilities
        Route::get('abilities', [UserController::class, 'getAllAvailableAbilities']);
    });

    // Location management (LocationController)
    Route::prefix('locations')->group(function () {
        Route::apiResource('', LocationController::class);
        Route::post('{location}/toggle-active', [LocationController::class, 'toggleActive']);
    });
    
    // Product management (ProductController)
    Route::apiResource('products', ProductController::class);
});
// =================================================================
// ROUTE_BLOCK_WMS_CORE_START (WMS 核心交易模組)
// =================================================================

// 權限要求：必須登入
Route::prefix('wms')->middleware('auth:sanctum')->group(function () {
    
    // Inbound (入庫管理)
    Route::prefix('inbound')->controller(InboundController::class)->group(function () {
        Route::post('create', 'store')->middleware('ability:inbound-create');
        // Putaway 涉及到實際庫存變動，需要專門權限
        Route::post('{inbound}/putaway', 'putaway')->middleware('ability:inbound-putaway');
    });

    // Outbound (出庫管理)
    Route::prefix('outbound')->controller(OutboundController::class)->group(function () {
        Route::post('create', 'store')->middleware('ability:outbound-create');
        // Ship 涉及最終結單，需要專門權限
        Route::post('{outbound}/ship', 'ship')->middleware('ability:outbound-ship');
    });

    // Picking (揀貨管理)
    Route::prefix('picking')->controller(PickingController::class)->group(function () {
        Route::post('create', 'store')->middleware('ability:picking-create'); // 自動或手動創建揀貨單
        // Scan 涉及到實際庫存扣減，需要專門權限
        Route::post('{picking}/scan', 'scan')->middleware('ability:picking-scan');
    });
});

// =================================================================
// ROUTE_BLOCK_WMS_CORE_END
// =================================================================
