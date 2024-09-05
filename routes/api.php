<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountingAccountsController;
use App\Http\Controllers\AccountingTransactionController;
use App\Http\Controllers\Auth\LoginController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/masuk', [LoginController::class, 'masuk']);
// Route::get('/accounting/accounts', [AccountingAccountsController::class, 'accounts']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);

    Route::prefix('accounting')->group(function () {
        Route::get('/subaccounts', [AccountingAccountsController::class, 'subaccounts']);
        Route::get('/accounts', [AccountingAccountsController::class, 'accounts']);
        Route::get('/accounts/{id}', [AccountingAccountsController::class, 'accountDetail']);
        Route::post('/accounts/init', [AccountingAccountsController::class, 'initAccounts']);
        Route::post('/accounts/upsert', [AccountingAccountsController::class, 'upsert']);
        Route::post('/subaccounts/upsert', [AccountingAccountsController::class, 'upsertSubaccount']);
    
        Route::get('/transactions', [AccountingTransactionController::class, 'transactions']);
        Route::get('/transactions/all', [AccountingTransactionController::class, 'allTransactions']);
        Route::get('/transactions/{id}', [AccountingTransactionController::class, 'transaction']);
        Route::post('/transactions', [AccountingTransactionController::class, 'upsert']);
    });


});

