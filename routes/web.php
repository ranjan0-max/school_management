<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Platform\AuditLogController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\RoleController;
use App\Http\Controllers\Platform\SchoolController;
use App\Http\Controllers\Platform\UserController;
use App\Http\Controllers\School\AcademicSessionController;
use App\Http\Controllers\School\DashboardController as SchoolDashboardController;
use App\Http\Controllers\School\EmployeeController;
use App\Http\Controllers\School\GuardianController;
use App\Http\Controllers\School\PeriodController;
use App\Http\Controllers\School\SchoolClassController;
use App\Http\Controllers\School\SectionController;
use App\Http\Controllers\School\StudentController;
use App\Http\Controllers\School\SubjectController;
use App\Http\Controllers\School\TimetableEntryController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');

    Route::middleware('super.admin')->prefix('platform')->name('platform.')->group(function () {
        Route::get('dashboard', PlatformDashboardController::class)->name('dashboard');
        Route::get('audit-logs', AuditLogController::class)->name('audit-logs.index');

        Route::resource('schools', SchoolController::class)->except(['show', 'destroy']);
        Route::get('schools/{school}/access', [SchoolController::class, 'access'])->name('schools.access');
        Route::put('schools/{school}/access', [SchoolController::class, 'updateAccess'])->name('schools.access.update');
        Route::post('schools/{school}/enter', [SchoolController::class, 'enter'])->name('schools.enter');
        Route::delete('school-context', [SchoolController::class, 'leave'])->name('schools.leave');

        Route::get('roles/options', [RoleController::class, 'options'])->name('roles.options');
        Route::resource('roles', RoleController::class)->except(['show', 'destroy']);
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
    });

    // School workspace: the user's own school (or the school a Super Admin entered).
    // Each controller adds its own menu:<key>,<action> access checks.
    Route::middleware(['tenant.resolve', 'school.active'])->prefix('school')->name('school.')->group(function () {
        Route::get('dashboard', SchoolDashboardController::class)->name('dashboard');

        Route::resource('academic-sessions', AcademicSessionController::class)->except(['show']);
        Route::patch('academic-sessions/{academic_session}/current', [AcademicSessionController::class, 'makeCurrent'])
            ->name('academic-sessions.current');
        Route::resource('classes', SchoolClassController::class)->except(['show'])->parameters(['classes' => 'schoolClass']);
        Route::resource('sections', SectionController::class)->except(['show']);
        Route::resource('subjects', SubjectController::class)->except(['show']);
        Route::resource('periods', PeriodController::class)->except(['show']);
        Route::get('timetable', [TimetableEntryController::class, 'index'])->name('timetable.index');
        Route::put('timetable', [TimetableEntryController::class, 'update'])->name('timetable.update');

        Route::resource('guardians', GuardianController::class)->except(['show']);
        Route::resource('students', StudentController::class)->only(['index', 'create', 'store', 'edit', 'update']);

        // One controller for both lists; the route name decides teacher vs staff.
        Route::resource('teachers', EmployeeController::class)->only(['index', 'create', 'store', 'edit', 'update'])
            ->parameters(['teachers' => 'employee']);
        Route::resource('staff', EmployeeController::class)->only(['index', 'create', 'store', 'edit', 'update'])
            ->parameters(['staff' => 'employee']);
    });
});
