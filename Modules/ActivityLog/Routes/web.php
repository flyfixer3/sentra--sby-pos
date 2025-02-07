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
use Modules\ActivityLog\Http\Controllers\ActivityLogController;

Route::middleware(['auth'])->prefix('logs')->group(function () {
    Route::get('/', [ActivityLogController::class, 'index'])->name('logs.index');
});
