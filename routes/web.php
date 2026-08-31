<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CollaboratorController;
use App\Http\Controllers\EmploymentConditionController;
use App\Http\Controllers\JobRoleController;
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
});
