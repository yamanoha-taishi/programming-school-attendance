<?php

use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\GuardianController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StudentController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth:guardian,staff'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth:staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/attendances', [AttendanceController::class, 'index'])->name('attendances.index');
    Route::patch('/lessons/{lesson}/students/{student}/attendance', [AttendanceController::class, 'update'])->name('attendances.update');

    Route::middleware('role:full_access')->group(function () {
        Route::get('/menu', [MenuController::class, 'index'])->name('menu');
        Route::post('/guardians/{guardian}/reissue-password', [GuardianController::class, 'reissuePassword'])->name('guardians.reissue-password');
        Route::resource('guardians', GuardianController::class)->except('show');
        Route::resource('students', StudentController::class)->except('show');
        Route::post('/staff/{staff}/reissue-password', [StaffController::class, 'reissuePassword'])->name('staff.reissue-password');
        Route::resource('staff', StaffController::class)->except('show');
    });
});

require __DIR__.'/auth.php';
