<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DayClosingController;
use App\Http\Controllers\Web\DeviceController;
use App\Http\Controllers\Web\PersonnelController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\SettingController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\VoidController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'session.limit', 'active', 'owner'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/reports/daily', [ReportController::class, 'daily'])->name('reports.daily');
    Route::get('/reports/daily/export', [ReportController::class, 'dailyExport'])->name('reports.daily.export');
    Route::get('/reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
    Route::get('/reports/monthly/export', [ReportController::class, 'monthlyExport'])->name('reports.monthly.export');

    Route::get('/closings', [DayClosingController::class, 'index'])->name('closings.index');
    Route::get('/closings/{daySession}', [DayClosingController::class, 'show'])->name('closings.show');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

    Route::resource('personnel', PersonnelController::class)->except(['show', 'destroy'])->parameters(['personnel' => 'personnel']);
    Route::resource('users', UserController::class)->except(['show', 'destroy']);

    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::put('/settings/void-code', [SettingController::class, 'updateVoidCode'])->name('settings.void-code');
    Route::get('/voids', [VoidController::class, 'index'])->name('voids.index');
});
