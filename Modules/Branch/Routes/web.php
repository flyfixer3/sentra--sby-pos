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
use App\Http\Controllers\BranchSwitchController;

Route::middleware(['auth'])->group(function () {
    Route::resource('branches', BranchController::class);
});


Route::post('/switch-branch', [BranchSwitchController::class, 'switch'])->name('switch-branch');
