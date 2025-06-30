<?php

use Illuminate\Support\Facades\Route;
use Modules\Transfer\Http\Controllers\TransferController;
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

// Route::prefix('transfer')->group(function() {
//     Route::get('/', 'TransferController@index');
// });


Route::middleware(['auth'])->prefix('transfers')->name('transfers.')->group(function () {
    Route::get('/', [TransferController::class, 'index'])->name('index');
    Route::get('/create', [TransferController::class, 'create'])->name('create');
    Route::post('/', [TransferController::class, 'store'])->name('store');
    Route::post('/{transfer}/confirm', [TransferController::class, 'confirm'])->name('confirm');
});
