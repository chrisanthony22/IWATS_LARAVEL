<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AgentSalesController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\UserAccountController;

Route::get('/', fn () => redirect()->route('login'));

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/cancelled', [DashboardController::class, 'cancelledTrips'])->name('dashboard.cancelled');
    Route::get('/agent-sales', [AgentSalesController::class, 'index'])->name('agent-sales.dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->prefix('logs')->name('logs.')->group(function () {
    Route::get('audit', [AuditLogController::class, 'index'])->name('audit');
    Route::get('audit/export', [AuditLogController::class, 'export'])->name('audit.export');
});

Route::middleware(['auth'])->prefix('users')->name('users.')->group(function () {
    Route::get('/',                      [UserAccountController::class, 'index'])->name('index');
    Route::post('/',                     [UserAccountController::class, 'store'])->name('store');
    Route::put('/{user}',                [UserAccountController::class, 'update'])->name('update');
    Route::post('/{user}/reset-password', [UserAccountController::class, 'resetPassword'])->name('reset-password');
    Route::delete('/{user}',             [UserAccountController::class, 'destroy'])->name('destroy');
});

require __DIR__.'/auth.php';