<?php

use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StudentDashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('student/dashboard', StudentDashboardController::class)
        ->middleware('student')
        ->name('student.dashboard');

    Route::middleware('super.admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('roles', [RolePermissionController::class, 'index'])->name('roles.index');
        Route::post('roles', [RolePermissionController::class, 'store'])->name('roles.store');
        Route::put('roles/{role}', [RolePermissionController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RolePermissionController::class, 'destroy'])->name('roles.destroy');
    });
});

require __DIR__.'/settings.php';
