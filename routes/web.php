<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\CustomerSyncController;

Route::post('/sync-axapta', [CustomerSyncController::class, 'syncFromAxapta'])->name('sync.axapta');

// Route untuk membuka halaman tampilan customers
Route::get('/customers', function () {
    return view('customers');
});

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';