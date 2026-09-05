<?php

use App\Http\Controllers\AttendanceCalculationController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\AttendanceInterpretationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\BiometricImportController;
use App\Http\Controllers\CollaboratorController;
use App\Http\Controllers\ControlPeriodController;
use App\Http\Controllers\EmploymentConditionController;
use App\Http\Controllers\JobRoleController;
use App\Http\Controllers\RemunerationCalculationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/iniciar-sesion', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/iniciar-sesion', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/olvide-mi-contrasena', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/olvide-mi-contrasena', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/restablecer-contrasena', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::view('/panel', 'dashboard')->name('dashboard');
    Route::post('/cerrar-sesion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('can:manage-users')->group(function () {
        Route::resource('usuarios', UserController::class)
            ->except(['show', 'destroy'])
            ->parameters(['usuarios' => 'user'])
            ->names('users');
    });

    Route::middleware('can:manage-collaborators')->group(function () {
        Route::resource('colaboradores', CollaboratorController::class)
            ->except(['destroy'])
            ->parameters(['colaboradores' => 'collaborator'])
            ->names('collaborators');
        Route::post('colaboradores/{collaborator}/condiciones', [EmploymentConditionController::class, 'store'])
            ->name('collaborators.conditions.store');
        Route::patch('colaboradores/{collaborator}/estado', [CollaboratorController::class, 'toggleStatus'])
            ->name('collaborators.toggle-status');
    });

    Route::middleware('can:manage-job-roles')->group(function () {
        Route::resource('roles-laborales', JobRoleController::class)
            ->except(['show', 'destroy'])
            ->parameters(['roles-laborales' => 'job_role'])
            ->names('job-roles');
        Route::patch('roles-laborales/{job_role}/estado', [JobRoleController::class, 'toggleStatus'])
            ->name('job-roles.toggle-status');
    });

    Route::middleware('can:manage-control-periods')->group(function () {
        Route::resource('periodos-control', ControlPeriodController::class)
            ->except(['show', 'destroy'])
            ->parameters(['periodos-control' => 'control_period'])
            ->names('control-periods');
    });

    Route::middleware('can:import-biometric-data')->group(function () {
        Route::get('importaciones-biometrico', [BiometricImportController::class, 'index'])->name('biometric-imports.index');
        Route::post('importaciones-biometrico', [BiometricImportController::class, 'store'])->name('biometric-imports.store');
        Route::get('importaciones-biometrico/{biometric_import}', [BiometricImportController::class, 'show'])->name('biometric-imports.show');
        Route::post('importaciones-biometrico/{biometric_import}/interpretacion', [AttendanceInterpretationController::class, 'store'])->name('biometric-imports.interpretation.store');
        Route::get('importaciones-biometrico/{biometric_import}/interpretacion', [AttendanceInterpretationController::class, 'show'])->name('biometric-imports.interpretation.show');
    });

    Route::middleware('can:manage-attendance-corrections')->group(function () {
        Route::get('inconsistencias', [AttendanceCorrectionController::class, 'index'])->name('attendance-corrections.index');
        Route::post('inconsistencias/{attendance_interpretation}', [AttendanceCorrectionController::class, 'store'])->name('attendance-corrections.store');
        Route::delete('inconsistencias/{attendance_interpretation}', [AttendanceCorrectionController::class, 'destroy'])->name('attendance-corrections.destroy');
    });

    Route::middleware('can:calculate-attendance')->group(function () {
        Route::get('calculo-horas', [AttendanceCalculationController::class, 'index'])->name('attendance-calculations.index');
        Route::post('calculo-horas/{control_period}', [AttendanceCalculationController::class, 'store'])->name('attendance-calculations.store');
    });

    Route::middleware('can:calculate-remunerations')->group(function () {
        Route::get('remuneraciones', [RemunerationCalculationController::class, 'index'])->name('remunerations.index');
        Route::post('remuneraciones/{control_period}', [RemunerationCalculationController::class, 'store'])->name('remunerations.store');
    });
});
