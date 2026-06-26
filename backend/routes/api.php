<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminCatalogController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DirectUploadController;
use App\Http\Controllers\Api\EvidenceController;
use App\Http\Controllers\Api\EvidenceSubmissionController;
use App\Http\Controllers\Api\EvidenceTaskController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\MyEvidenceController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::get('/openapi.json', function () {
    $path = storage_path('api-docs/api-docs.json');

    if (! file_exists($path)) {
        return response()->json(['message' => 'OpenAPI documentation has not been generated.'], 404)
            ->header('Access-Control-Allow-Origin', '*');
    }

    return response()->json(json_decode(file_get_contents($path), true))
        ->header('Content-Type', 'application/json')
        ->header('Access-Control-Allow-Origin', '*');
});
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/change-password', [ProfileController::class, 'changePassword']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/faculties', [CatalogController::class, 'faculties']);
    Route::get('/programs', [CatalogController::class, 'programs']);
    Route::get('/study-plans', [CatalogController::class, 'studyPlans']);
    Route::get('/semesters', [CatalogController::class, 'semesters']);
    Route::get('/courses', [CatalogController::class, 'courses']);
    Route::get('/teachers', [CatalogController::class, 'teachers']);
    Route::get('/accreditation-models', [CatalogController::class, 'accreditationModels']);
    Route::get('/accreditation-cycles', [CatalogController::class, 'accreditationCycles']);
    Route::get('/accreditation-criteria', [CatalogController::class, 'criteria']);
    Route::get('/evidence-requirements', [CatalogController::class, 'evidenceRequirements']);
    Route::get('/evidence-tasks/catalog', [CatalogController::class, 'evidenceTasks']);
    Route::post('/uploads/direct/presign', [DirectUploadController::class, 'presign'])->middleware('permission:create.evidences');
    Route::post('/uploads/direct/complete', [DirectUploadController::class, 'complete'])->middleware('permission:create.evidences');

    Route::prefix('catalog')->group(function () {
        Route::get('/faculties', [CatalogController::class, 'faculties']);
        Route::get('/programs', [CatalogController::class, 'programs']);
        Route::get('/study-plans', [CatalogController::class, 'studyPlans']);
        Route::get('/semesters', [CatalogController::class, 'semesters']);
        Route::get('/courses', [CatalogController::class, 'courses']);
        Route::get('/teachers', [CatalogController::class, 'teachers']);
        Route::get('/accreditation-models', [CatalogController::class, 'accreditationModels']);
        Route::get('/accreditation-cycles', [CatalogController::class, 'accreditationCycles']);
        Route::get('/criteria', [CatalogController::class, 'criteria']);
        Route::get('/accreditation-criteria', [CatalogController::class, 'criteria']);
        Route::get('/evidence-requirements', [CatalogController::class, 'evidenceRequirements']);
        Route::get('/evidence-tasks', [CatalogController::class, 'evidenceTasks']);
    });

    Route::prefix('admin')->middleware('permission:manage.catalogs')->group(function () {
        Route::get('/users', [AdminCatalogController::class, 'users']);
        Route::post('/users', [AdminCatalogController::class, 'storeUser']);
        Route::put('/users/{user}', [AdminCatalogController::class, 'updateUser']);
        Route::delete('/users/{user}', [AdminCatalogController::class, 'destroyUser']);
        Route::get('/roles', [AdminCatalogController::class, 'roles']);

        Route::get('/institutions', [AdminCatalogController::class, 'institutions']);
        Route::post('/institutions', [AdminCatalogController::class, 'storeInstitution']);
        Route::put('/institutions/{institution}', [AdminCatalogController::class, 'updateInstitution']);
        Route::delete('/institutions/{institution}', [AdminCatalogController::class, 'destroyInstitution']);

        Route::get('/faculties', [AdminCatalogController::class, 'faculties']);
        Route::post('/faculties', [AdminCatalogController::class, 'storeFaculty']);
        Route::put('/faculties/{faculty}', [AdminCatalogController::class, 'updateFaculty']);
        Route::delete('/faculties/{faculty}', [AdminCatalogController::class, 'destroyFaculty']);

        Route::get('/programs', [AdminCatalogController::class, 'programs']);
        Route::post('/programs', [AdminCatalogController::class, 'storeProgram']);
        Route::put('/programs/{program}', [AdminCatalogController::class, 'updateProgram']);
        Route::delete('/programs/{program}', [AdminCatalogController::class, 'destroyProgram']);

        Route::get('/study-plans', [AdminCatalogController::class, 'studyPlans']);
        Route::post('/study-plans', [AdminCatalogController::class, 'storeStudyPlan']);
        Route::put('/study-plans/{studyPlan}', [AdminCatalogController::class, 'updateStudyPlan']);
        Route::delete('/study-plans/{studyPlan}', [AdminCatalogController::class, 'destroyStudyPlan']);

        Route::get('/courses', [AdminCatalogController::class, 'courses']);
        Route::post('/courses', [AdminCatalogController::class, 'storeCourse']);
        Route::put('/courses/{course}', [AdminCatalogController::class, 'updateCourse']);
        Route::delete('/courses/{course}', [AdminCatalogController::class, 'destroyCourse']);

        Route::get('/course-offerings', [AdminCatalogController::class, 'courseOfferings']);
        Route::post('/course-offerings', [AdminCatalogController::class, 'storeCourseOffering']);
        Route::put('/course-offerings/{courseOffering}', [AdminCatalogController::class, 'updateCourseOffering']);
        Route::delete('/course-offerings/{courseOffering}', [AdminCatalogController::class, 'destroyCourseOffering']);

        Route::get('/teachers', [AdminCatalogController::class, 'teachers']);
        Route::post('/teachers', [AdminCatalogController::class, 'storeTeacher']);
        Route::put('/teachers/{teacher}', [AdminCatalogController::class, 'updateTeacher']);
        Route::delete('/teachers/{teacher}', [AdminCatalogController::class, 'destroyTeacher']);
        Route::post('/teachers/{teacher}/user', [AdminCatalogController::class, 'createTeacherUser']);
        Route::post('/teachers/{teacher}/cv', [AdminCatalogController::class, 'uploadTeacherCv']);

        Route::get('/accreditation-criteria', [AdminCatalogController::class, 'accreditationCriteria']);
        Route::post('/accreditation-criteria', [AdminCatalogController::class, 'storeAccreditationCriterion']);
        Route::put('/accreditation-criteria/{criterion}', [AdminCatalogController::class, 'updateAccreditationCriterion']);
        Route::delete('/accreditation-criteria/{criterion}', [AdminCatalogController::class, 'destroyAccreditationCriterion']);

        Route::get('/accreditation-subcriteria', [AdminCatalogController::class, 'accreditationSubcriteria']);
        Route::post('/accreditation-subcriteria', [AdminCatalogController::class, 'storeAccreditationSubcriterion']);
        Route::put('/accreditation-subcriteria/{subcriterion}', [AdminCatalogController::class, 'updateAccreditationSubcriterion']);
        Route::delete('/accreditation-subcriteria/{subcriterion}', [AdminCatalogController::class, 'destroyAccreditationSubcriterion']);

        Route::get('/evidence-requirements', [AdminCatalogController::class, 'evidenceRequirements']);
        Route::post('/evidence-requirements', [AdminCatalogController::class, 'storeEvidenceRequirement']);
        Route::put('/evidence-requirements/{requirement}', [AdminCatalogController::class, 'updateEvidenceRequirement']);
        Route::delete('/evidence-requirements/{requirement}', [AdminCatalogController::class, 'destroyEvidenceRequirement']);
    });

    Route::prefix('dashboard')->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/progress-by-criterion', [DashboardController::class, 'progressByCriterion']);
        Route::get('/progress-by-program', [DashboardController::class, 'progressByProgram']);
        Route::get('/pending-by-teacher', [DashboardController::class, 'pendingByTeacher']);
        Route::get('/teacher-evidence-status', [DashboardController::class, 'teacherEvidenceStatus']);
    })->middleware('permission:view.dashboard');

    Route::get('/evidences', [EvidenceController::class, 'index'])->middleware('permission:view.evidences');
    Route::post('/evidences', [EvidenceController::class, 'store'])->middleware('permission:create.evidences');
    Route::get('/evidences/{evidence}', [EvidenceController::class, 'show'])->middleware('permission:view.evidences');
    Route::post('/evidences/{evidence}/versions', [EvidenceController::class, 'version'])->middleware('permission:create.evidences');
    Route::post('/evidences/{evidence}/observe', [EvidenceController::class, 'observe'])->middleware('permission:review.evidences');
    Route::post('/evidences/{evidence}/validate', [EvidenceController::class, 'validateEvidence'])->middleware('permission:validate.evidences');
    Route::post('/evidences/{evidence}/approve', [EvidenceController::class, 'approve'])->middleware('permission:approve.evidences');
    Route::delete('/evidences/{evidence}', [EvidenceController::class, 'destroy'])->middleware('permission:manage.accreditation');

    Route::get('/evidence-tasks', [EvidenceTaskController::class, 'index']);
    Route::get('/evidence-tasks/{evidenceTask}', [EvidenceTaskController::class, 'show']);
    Route::patch('/evidence-tasks/{evidenceTask}/status', [EvidenceTaskController::class, 'updateStatus']);
    Route::post('/evidence-tasks/{evidenceTask}/assign', [EvidenceTaskController::class, 'assign']);

    Route::post('/evidence-tasks/{evidenceTask}/submissions', [EvidenceSubmissionController::class, 'store']);
    Route::get('/evidence-submissions/{evidenceSubmission}', [EvidenceSubmissionController::class, 'show']);
    Route::post('/evidence-submissions/{evidenceSubmission}/reviews', [EvidenceSubmissionController::class, 'review']);

    Route::get('/my/evidence-tasks', [MyEvidenceController::class, 'tasks']);

    Route::post('/exports/evidences-zip', [ExportController::class, 'evidencesZip'])->middleware('permission:export.evidences');
});
