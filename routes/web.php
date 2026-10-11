<?php

use App\Http\Controllers\AgentSalesController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleAccessController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserAccountController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// Unrestricted Authenticated Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Protected Authenticated Routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Main Dashboard (Keep without module middleware so users can log in safely)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/cancelled', [DashboardController::class, 'cancelledTrips'])->name('dashboard.cancelled');

    // Agent Sales
    Route::get('/agent-sales', [AgentSalesController::class, 'index'])
        ->middleware('module:agent-sales,view')
        ->name('agent-sales.dashboard');

    // Audit Logs
    Route::prefix('logs')->name('logs.')->group(function () {
        Route::get('audit', [AuditLogController::class, 'index'])
            ->middleware('module:audit-logs,view')
            ->name('audit');
            
        Route::get('audit/export', [AuditLogController::class, 'export'])
            ->middleware('module:audit-logs,view')
            ->name('audit.export');
    });

    // User Accounts
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserAccountController::class, 'index'])
            ->middleware('module:users,view')
            ->name('index');

        Route::post('/', [UserAccountController::class, 'store'])
            ->middleware('module:users,create')
            ->name('store');

        Route::put('/{user}', [UserAccountController::class, 'update'])
            ->middleware('module:users,edit')
            ->name('update');

        Route::post('/{user}/reset-password', [UserAccountController::class, 'resetPassword'])
            ->middleware('module:users,edit')
            ->name('reset-password');

        Route::delete('/{user}', [UserAccountController::class, 'destroy'])
            ->middleware('module:users,delete')
            ->name('destroy');
    });

    // Module Access Management
    Route::prefix('module-access')->name('module-access.')->group(function () {
        Route::get('/', [ModuleAccessController::class, 'index'])
            ->middleware('module:module-access,view')
            ->name('index');

        Route::put('/', [ModuleAccessController::class, 'update'])
            ->middleware('module:module-access,edit')
            ->name('update');
    });
});

require __DIR__.'/auth.php';