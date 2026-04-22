<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\Controllers\Api\CtaClicksController;
use Modules\Crm\Http\Controllers\Api\LeadsController;
use Modules\Crm\Http\Controllers\Api\ProductsController;
use Modules\Crm\Http\Controllers\Api\ServiceOrdersController;
use Modules\Crm\Http\Controllers\Api\TechniciansController;
use Modules\Crm\Http\Controllers\Api\ServiceOrderPhotosController;
use Modules\Crm\Http\Controllers\Api\WarrantiesController;
use Modules\Crm\Http\Controllers\Api\ReportsController;
use Modules\Crm\Http\Controllers\Api\UsersController;
use Modules\Crm\Http\Controllers\Api\NotificationsController;
use Modules\Crm\Http\Controllers\Api\CustomersController;
use Modules\Crm\Http\Controllers\Api\PermissionsController;

/*
|--------------------------------------------------------------------------
| API Routes (CRM)
|--------------------------------------------------------------------------
*/

Route::middleware([
    'api',
    Modules\Crm\Http\Middleware\ForceJsonResponse::class,
])->post('/tracking/cta-click', [CtaClicksController::class, 'store']);

$middlewares = [
    'api',
    Modules\Crm\Http\Middleware\ForceJsonResponse::class,
    'auth:sanctum',
    Modules\Crm\Http\Middleware\EnsureCrmAccess::class,
    Modules\Crm\Http\Middleware\ResolveBranchFromHeader::class,
];

Route::middleware([
    'api',
    Modules\Crm\Http\Middleware\ForceJsonResponse::class,
    'auth:sanctum',
    Modules\Crm\Http\Middleware\EnsureCrmAccess::class,
])->prefix('crm')->group(function () {
    Route::get('/notifications', [NotificationsController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationsController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationsController::class, 'markRead']);
    Route::get('/permissions', [PermissionsController::class, 'index']);
    Route::put('/permissions/roles/{roleId}', [PermissionsController::class, 'updateRole']);
});

Route::middleware($middlewares)->prefix('crm')->group(function () {
    Route::get('/users', [UsersController::class, 'index']);

    Route::get('/cta-clicks', [CtaClicksController::class, 'index']);
    Route::get('/products', [ProductsController::class, 'index']);
    Route::get('/customers', [CustomersController::class, 'index']);

    // Leads
    Route::get('/leads', [LeadsController::class, 'index']);
    Route::post('/leads', [LeadsController::class, 'store']);
    Route::get('/leads/{id}', [LeadsController::class, 'show']);
    Route::patch('/leads/{id}', [LeadsController::class, 'update']);
    Route::delete('/leads/{id}', [LeadsController::class, 'destroy']);
    Route::post('/leads/{id}/convert', [LeadsController::class, 'convert']);
    Route::get('/leads/{id}/sales-url', [LeadsController::class, 'salesUrl']);

    // Lead comments & timeline
    Route::get('/leads/{id}/comments', [Modules\Crm\Http\Controllers\Api\LeadCommentsController::class, 'index']);
    Route::post('/leads/{id}/comments', [Modules\Crm\Http\Controllers\Api\LeadCommentsController::class, 'store']);
    Route::delete('/leads/{id}/comments/{commentId}', [Modules\Crm\Http\Controllers\Api\LeadCommentsController::class, 'destroy']);
    Route::get('/leads/{id}/timeline', [Modules\Crm\Http\Controllers\Api\LeadTimelineController::class, 'index']);

    // Service Orders
    Route::get('/service-orders', [ServiceOrdersController::class, 'index']);
    Route::post('/service-orders', [ServiceOrdersController::class, 'store']);
    Route::get('/service-orders/{id}', [ServiceOrdersController::class, 'show']);
    Route::patch('/service-orders/{id}', [ServiceOrdersController::class, 'update']);
    Route::delete('/service-orders/{id}', [ServiceOrdersController::class, 'destroy']);

    // Technicians
    Route::post('/service-orders/{id}/assign-technicians', [TechniciansController::class, 'assign']);
    Route::post('/service-orders/{id}/accept', [TechniciansController::class, 'accept']);
    Route::post('/service-orders/{id}/start', [TechniciansController::class, 'start']);
    Route::post('/service-orders/{id}/complete', [TechniciansController::class, 'complete']);
    // Technicians list for active branch
    Route::get('/technicians', [TechniciansController::class, 'available']);

    // Photos
    Route::post('/service-orders/{id}/photos', [ServiceOrderPhotosController::class, 'store']);
    Route::get('/service-orders/{id}/photos', [ServiceOrderPhotosController::class, 'index']);
    Route::delete('/photos/{photoId}', [ServiceOrderPhotosController::class, 'destroy']);

    // Warranty
    Route::get('/service-orders/{id}/warranty', [WarrantiesController::class, 'show']);
    Route::put('/service-orders/{id}/warranty', [WarrantiesController::class, 'upsert']);

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/advanced', [ReportsController::class, 'advanced']);
        Route::get('/summary', [ReportsController::class, 'summary']);
        Route::get('/cta-tracking', [ReportsController::class, 'ctaTracking']);
        Route::get('/by-source', [ReportsController::class, 'bySource']);
        Route::get('/by-branch', [ReportsController::class, 'byBranch']);
        Route::get('/by-assignee', [ReportsController::class, 'byAssignee']);
        Route::get('/technician-performance', [ReportsController::class, 'technicianPerformance']);
    });
});
