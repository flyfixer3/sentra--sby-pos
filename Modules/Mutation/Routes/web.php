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
    Route::get('mutations/create', [MutationController::class, 'create'])->name('mutations.create');
    Route::post('mutations', [MutationController::class, 'store'])->name('mutations.store');
    Route::get('mutations/{mutation}', [MutationController::class, 'show'])->name('mutations.show');

});
