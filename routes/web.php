<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SwitchBranchController;
use App\Http\Controllers\InboxController;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('auth.login');
})->middleware('guest');

Auth::routes(['register' => false]);

Route::group(['middleware' => 'auth'], function () {
    Route::get('/home', 'HomeController@index')->name('home');
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::resource('entities', 'EntityController')->except('show');

    Route::post('/session/keep-alive', function () {
        return response()->json(['ok' => true]);
    })->name('session.keep-alive');

    Route::get('/sales-purchases/chart-data', 'HomeController@salesPurchasesChart')
        ->name('sales-purchases.chart');

    Route::get('/current-month/chart-data', 'HomeController@currentMonthChart')
        ->name('current-month.chart');

    Route::get('/payment-flow/chart-data', 'HomeController@paymentChart')
        ->name('payment-flow.chart');

    /**
     * Switch Active Branch (session)
     * - User may switch only to branches allowed by BranchContext.
     * - Option "all" hanya untuk user yang punya permission view_all_branches.
     */
    Route::post('/switch-branch', [SwitchBranchController::class, 'switch'])
        ->name('switch-branch');
});
