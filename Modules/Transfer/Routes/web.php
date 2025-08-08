<?php

use Illuminate\Support\Facades\Route;
use Modules\Transfer\Http\Controllers\TransferController;

Route::middleware(['auth'])->prefix('transfers')->name('transfers.')->group(function () {

    // Halaman daftar semua transfer
    Route::get('/', [TransferController::class, 'index'])->name('index');

    // Buat transfer baru
    Route::get('/create', [TransferController::class, 'create'])->name('create');
    Route::post('/', [TransferController::class, 'store'])->name('store');

    // Detail transfer
    Route::get('/{transfer}/show', [TransferController::class, 'show'])->name('show');

    // ✅ Menampilkan form konfirmasi
    Route::get('/{transfer}/confirm', [TransferController::class, 'showConfirmationForm'])->name('confirm');

    // ✅ Menyimpan hasil konfirmasi transfer
    Route::put('/{transfer}/confirm', [TransferController::class, 'storeConfirmation'])->name('confirm.store');
    Route::get('/{transfer}/print-pdf', [TransferController::class, 'printPdf'])->name('print.pdf');


    // (Opsional) Route lama bisa dihapus atau di-rename jika tidak dipakai
    // Route::post('/{transfer}/confirm', [TransferController::class, 'confirm'])->name('confirm.quick');
    Route::delete('/{id}', [TransferController::class, 'destroy'])->name('destroy'); // 🧩 ini penting

});
