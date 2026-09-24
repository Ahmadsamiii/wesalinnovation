<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UserActivationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserInvitationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SectionController;
use Illuminate\Support\Facades\Route;

/* نظام داخلي بلا صفحة تعريفية عامة — الجذر يوجّه مباشرة للوحة أو الدخول. */
Route::get('/', function () {
    return redirect()->to(auth()->check() ? '/dashboard' : '/login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/sections/{tab}', SectionController::class)->name('sections.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    /* مدير النظام: الحسابات والسجل. */
    Route::middleware('role:sysadmin')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::post('users/{user}/deactivate', [UserActivationController::class, 'destroy'])->name('users.deactivate');
        Route::post('users/{user}/reactivate', [UserActivationController::class, 'store'])->name('users.reactivate');
        Route::post('users/{user}/invitation', [UserInvitationController::class, 'store'])->name('users.invitation');

        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');
    });
});

require __DIR__.'/auth.php';
