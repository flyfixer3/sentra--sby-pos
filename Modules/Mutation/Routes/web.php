<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Support\Facades\Route;
use Modules\Mutation\Http\Controllers\MutationController;

Route::group(['middleware' => 'auth'], function () {
    //Product Mutation
    Route::get('mutations', [MutationController::class, 'index'])->name('mutations.index');
    
    // Route::get('mutations/{mutation}', [MutationController::class, 'show'])->name('mutations.show');
        /*
    |--------------------------------------------------------------------------
    | Manual Mutation Creation Disabled
    |--------------------------------------------------------------------------
    |
    | Mutation sekarang hanya boleh dibuat otomatis dari flow sistem:
    | - Purchase Delivery
    | - Sale Delivery
    | - Transfer
    | - Adjustment
    | - Rack Movement
    | - Opening Stock Import
    | - Stock Quality movement
    |
    | Karena itu route mutations/create dan mutations.store tidak dibuka lagi.
    | Method store() di MutationController tetap dibiarkan untuk sementara
    | sebagai fallback/history, tapi tidak diexpose melalui route.
    |
    */

    Route::get('mutations/{mutation}', [MutationController::class, 'show'])
        ->name('mutations.show');
});
