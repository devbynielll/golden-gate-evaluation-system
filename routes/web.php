<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\ClassSectionController;
use App\Http\Controllers\Admin\ClassImportController;
use App\Http\Controllers\Admin\CourseSearchController;
use App\Http\Controllers\Admin\CriteriaController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EvaluationReminderController;
use App\Http\Controllers\Admin\FacultyController;
use App\Http\Controllers\Admin\FacultyImportController;
use App\Http\Controllers\Admin\ImportBatchController;
use App\Http\Controllers\Admin\OfficeController;
use App\Http\Controllers\Admin\OfficeStaffController;
use App\Http\Controllers\Admin\StaffCriteriaController;
use App\Http\Controllers\Admin\OfficeImportController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\ProgramWorkspaceController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\StaffReportController;
use App\Http\Controllers\Admin\ReviewPeriodController;
use App\Http\Controllers\Admin\ScheduleCopyController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentImportController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\SubjectImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\EvaluationController;
use App\Http\Controllers\Student\StaffEvaluationController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// super admin only
Route::middleware(['auth', 'verified', 'role:super_admin'])->group(function () {
    Route::post('/admin/departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::put('/admin/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
    Route::delete('/admin/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

    Route::get('/admin/accounts', [AdminAccountController::class, 'index'])->name('accounts.index');
    Route::post('/admin/accounts', [AdminAccountController::class, 'store'])->name('accounts.store');
    Route::put('/admin/accounts/{account}', [AdminAccountController::class, 'update'])->name('accounts.update');
    Route::post('/admin/accounts/{account}/reset-password', [AdminAccountController::class, 'resetPassword'])->name('accounts.reset-password');
    Route::delete('/admin/accounts/{account}', [AdminAccountController::class, 'destroy'])->name('accounts.destroy');

    Route::get('/admin/offices', [OfficeController::class, 'index'])->name('offices.index');
    Route::post('/admin/offices', [OfficeController::class, 'store'])->name('offices.store');
    Route::put('/admin/offices/{office}', [OfficeController::class, 'update'])->name('offices.update');
    Route::delete('/admin/offices/{office}', [OfficeController::class, 'destroy'])->name('offices.destroy');
    Route::post('/admin/offices/import/preview', [OfficeImportController::class, 'preview'])->name('offices.import.preview');
    Route::post('/admin/offices/import', [OfficeImportController::class, 'store'])->name('offices.import.store');


    Route::post('/admin/review-periods', [ReviewPeriodController::class, 'store'])
        ->name('review-periods.store');
    Route::put('/admin/review-periods/{reviewPeriod}', [ReviewPeriodController::class, 'update'])
        ->name('review-periods.update');
    Route::patch('/admin/review-periods/{reviewPeriod}/activate', [ReviewPeriodController::class, 'activate'])
        ->name('review-periods.activate');
    Route::patch('/admin/review-periods/{reviewPeriod}/toggle-submission', [ReviewPeriodController::class, 'toggleSubmission'])
        ->name('review-periods.toggle-submission');
    Route::delete('/admin/review-periods/{reviewPeriod}', [ReviewPeriodController::class, 'destroy'])
        ->name('review-periods.destroy');
});

// super admin + the department admin
Route::middleware(['auth', 'verified', 'role:super_admin,department_admin'])->group(function () {
    // dept admins get redirected to their department
    Route::get('/admin/departments', [DepartmentController::class, 'index'])->name('departments.index');

    Route::group([], function () {
        Route::get('/admin/departments/{department}', [ProgramController::class, 'show'])->name('departments.show');
        Route::post('/admin/departments/{department}/programs', [ProgramController::class, 'store'])->name('departments.programs.store');
        Route::put('/admin/departments/{department}/programs/{program}', [ProgramController::class, 'update'])->name('departments.programs.update');
        Route::delete('/admin/departments/{department}/programs/{program}', [ProgramController::class, 'destroy'])->name('departments.programs.destroy');

        Route::get('/admin/departments/{department}/programs/{program}', [ProgramWorkspaceController::class, 'show'])->name('departments.programs.show');

        Route::post('/admin/departments/{department}/programs/{program}/subjects/import/preview', [SubjectImportController::class, 'preview'])->name('programs.subjects.import.preview');
        Route::post('/admin/departments/{department}/programs/{program}/subjects/import', [SubjectImportController::class, 'store'])->name('programs.subjects.import.store');
        Route::post('/admin/departments/{department}/programs/{program}/subjects', [SubjectController::class, 'store'])->name('programs.subjects.store');
        Route::put('/admin/departments/{department}/programs/{program}/subjects/{subject}', [SubjectController::class, 'update'])->name('programs.subjects.update');
        Route::delete('/admin/departments/{department}/programs/{program}/subjects/{subject}', [SubjectController::class, 'destroy'])->name('programs.subjects.destroy');

        Route::post('/admin/departments/{department}/programs/{program}/faculty', [FacultyController::class, 'store'])->name('programs.faculty.store');
        Route::put('/admin/departments/{department}/programs/{program}/faculty/{faculty}', [FacultyController::class, 'update'])->name('programs.faculty.update');
        Route::delete('/admin/departments/{department}/programs/{program}/faculty/{faculty}', [FacultyController::class, 'destroy'])->name('programs.faculty.destroy');
        Route::post('/admin/departments/{department}/programs/{program}/faculty/import/preview', [FacultyImportController::class, 'preview'])->name('programs.faculty.import.preview');
        Route::post('/admin/departments/{department}/programs/{program}/faculty/import', [FacultyImportController::class, 'store'])->name('programs.faculty.import.store');

        Route::post('/admin/departments/{department}/programs/{program}/classes/import/preview', [ClassImportController::class, 'preview'])->name('programs.classes.import.preview');
        Route::post('/admin/departments/{department}/programs/{program}/classes/import/validate', [ClassImportController::class, 'validateImport'])->name('programs.classes.import.validate');
        Route::post('/admin/departments/{department}/programs/{program}/classes/import', [ClassImportController::class, 'store'])->name('programs.classes.import.store');
        Route::post('/admin/departments/{department}/programs/{program}/classes', [ClassSectionController::class, 'store'])->name('programs.classes.store');
        Route::put('/admin/departments/{department}/programs/{program}/classes/{classSection}', [ClassSectionController::class, 'update'])->name('programs.classes.update');
        Route::delete('/admin/departments/{department}/programs/{program}/classes/{classSection}', [ClassSectionController::class, 'destroy'])->name('programs.classes.destroy');
        Route::post('/admin/departments/{department}/programs/{program}/schedule/copy', [ScheduleCopyController::class, 'store'])->name('programs.schedule.copy');

        Route::post('/admin/departments/{department}/programs/{program}/students', [StudentController::class, 'store'])->name('programs.students.store');
        Route::put('/admin/departments/{department}/programs/{program}/students/{student}', [StudentController::class, 'update'])->name('programs.students.update');
        Route::delete('/admin/departments/{department}/programs/{program}/students/{student}', [StudentController::class, 'destroy'])->name('programs.students.destroy');
        Route::post('/admin/departments/{department}/programs/{program}/students/import/preview', [StudentImportController::class, 'preview'])->name('programs.students.import.preview');
        Route::post('/admin/departments/{department}/programs/{program}/students/import/validate', [StudentImportController::class, 'validateImport'])->name('programs.students.import.validate');
        Route::post('/admin/departments/{department}/programs/{program}/students/import', [StudentImportController::class, 'store'])->name('programs.students.import.store');
    });



    Route::get('/admin/courses/search', [CourseSearchController::class, 'search'])
        ->name('courses.search');
    Route::get('/admin/courses/filters', [CourseSearchController::class, 'filters'])
        ->name('courses.filters');

    Route::get('/admin/reports/faculty', [ReportController::class, 'faculty'])->name('reports.faculty');
});

Route::middleware(['auth', 'verified', 'role:super_admin,department_admin,office_admin'])->group(function () {
    Route::get('/admin/import-batches/{importBatch}', [ImportBatchController::class, 'show'])->name('import-batches.show');
    Route::post('/admin/students/{student}/remind', [EvaluationReminderController::class, 'store'])
        ->name('students.remind');
    Route::post('/admin/evaluations/remind-all', [EvaluationReminderController::class, 'storeBulk'])
        ->name('evaluations.remind-all');


});

// super admin + the office admin
Route::middleware(['auth', 'verified', 'role:super_admin,office_admin'])->group(function () {
    Route::get('/admin/reports/staff', [StaffReportController::class, 'index'])->name('reports.staff');
    Route::get('/admin/reports/office', [ReportController::class, 'office'])->name('reports.office');
});

Route::middleware(['auth', 'verified', 'role:student'])->group(function () {
    Route::get('/evaluation', [EvaluationController::class, 'index'])->name('evaluation.index');
    Route::post('/evaluation/faculty/{classSubject}', [EvaluationController::class, 'storeFaculty'])
        ->name('evaluation.faculty.store');
    Route::post('/evaluation/staff/{staff}', [StaffEvaluationController::class, 'store'])->name('evaluation.staff.store');
    Route::post('/evaluation/office/{office}', [EvaluationController::class, 'storeOffice'])
        ->name('evaluation.office.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Each criteria type enforces its role and ownership again in the controller.
Route::middleware(['auth', 'verified', 'role:super_admin,department_admin'])->group(function () {
    Route::get('/admin/criteria/faculty', [CriteriaController::class, 'faculty'])->name('criteria.faculty');
});

Route::middleware(['auth', 'verified', 'role:super_admin,office_admin'])->group(function () {
    Route::get('/admin/criteria/office', [CriteriaController::class, 'office'])->name('criteria.office');
});

Route::middleware(['auth', 'verified', 'role:super_admin,department_admin,office_admin'])->group(function () {

    Route::pattern('type', 'faculty|office');
    Route::post('/admin/criteria/{type}/categories', [CriteriaController::class, 'storeCategory'])
        ->name('criteria.categories.store');
    Route::put('/admin/criteria/{type}/categories/{category}', [CriteriaController::class, 'updateCategory'])
        ->name('criteria.categories.update');
    Route::delete('/admin/criteria/{type}/categories/{category}', [CriteriaController::class, 'destroyCategory'])
        ->name('criteria.categories.destroy');

    Route::post('/admin/criteria/{type}/categories/{category}/questions', [CriteriaController::class, 'storeQuestion'])
        ->name('criteria.questions.store');
    Route::put('/admin/criteria/{type}/categories/{category}/questions/{question}', [CriteriaController::class, 'updateQuestion'])
        ->name('criteria.questions.update');
    Route::delete('/admin/criteria/{type}/categories/{category}/questions/{question}', [CriteriaController::class, 'destroyQuestion'])
        ->name('criteria.questions.destroy');

});


Route::middleware(['auth', 'verified', 'role:super_admin,office_admin'])->group(function () {
    Route::get('/admin/staff', [OfficeStaffController::class, 'index'])->name('staff.index');
    Route::get('/admin/offices/{office}/staff', [OfficeStaffController::class, 'index'])->name('offices.staff.index');
    Route::post('/admin/offices/{office}/staff', [OfficeStaffController::class, 'store'])->name('offices.staff.store');
    Route::put('/admin/offices/{office}/staff/{staff}', [OfficeStaffController::class, 'update'])->name('offices.staff.update');
    Route::patch('/admin/offices/{office}/staff/{staff}/archive', [OfficeStaffController::class, 'archive'])->name('offices.staff.archive');

    Route::get('/admin/criteria/staff', [StaffCriteriaController::class, 'index'])->name('criteria.staff');
    Route::post('/admin/staff-criteria/categories', [StaffCriteriaController::class, 'storeStaffCategory'])->name('staff-criteria.categories.store');
    Route::put('/admin/staff-criteria/categories/{staffCategory}', [StaffCriteriaController::class, 'updateStaffCategory'])->name('staff-criteria.categories.update');
    Route::delete('/admin/staff-criteria/categories/{staffCategory}', [StaffCriteriaController::class, 'destroyStaffCategory'])->name('staff-criteria.categories.destroy');
    Route::post('/admin/staff-criteria/categories/{staffCategory}/questions', [StaffCriteriaController::class, 'storeStaffQuestion'])->name('staff-criteria.questions.store');
    Route::put('/admin/staff-criteria/categories/{staffCategory}/questions/{staffQuestion}', [StaffCriteriaController::class, 'updateStaffQuestion'])->name('staff-criteria.questions.update');
    Route::delete('/admin/staff-criteria/categories/{staffCategory}/questions/{staffQuestion}', [StaffCriteriaController::class, 'destroyStaffQuestion'])->name('staff-criteria.questions.destroy');
});

Route::middleware(['auth', 'verified', 'role:super_admin,department_admin,office_admin'])->group(function () {
    Route::get('/admin/notifications', [\App\Http\Controllers\Admin\EvaluationNotificationController::class, 'index'])->name('admin-notifications.index');
    Route::patch('/admin/notifications/{notification}', [\App\Http\Controllers\Admin\EvaluationNotificationController::class, 'update'])
        ->whereUuid('notification')->name('admin-notifications.update');
});
