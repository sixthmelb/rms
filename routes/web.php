<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\RequestPdfController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/request/{request}/pdf', [RequestPdfController::class, 'downloadPdf'])
        ->name('request.pdf');
});

require __DIR__.'/auth.php';
