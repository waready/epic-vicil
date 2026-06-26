<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicProgram;
use App\Models\AccreditationCycle;
use App\Models\AccreditationCriterion;
use App\Models\AccreditationSubcriterion;
use App\Models\AcademicTerm;
use App\Models\CourseAssignment;
use App\Models\CourseOffering;
use App\Models\CurriculumCourse;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceTask;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\StudyPlan;
use App\Models\Teacher;
use App\Models\User;
use App\Services\EvidenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AdminCatalogController extends Controller
{
    public function institutions()
    {
        return response()->json(Institution::query()->orderBy('name')->get());
    }

    public function storeInstitution(Request $request)
    {
        $data = $this->validateInstitution($request);

        return response()->json(Institution::create($data), 201);
    }

    public function updateInstitution(Request $request, Institution $institution)
    {
        $data = $this->validateInstitution($request, $institution);
        $institution->update($data);

        return response()->json($institution->fresh());
    }

    public function destroyInstitution(Institution $institution)
    {
        $institution->delete();

        return response()->json(['message' => 'Institucion eliminada correctamente.']);
    }

    public function users()
    {
        $query = User::query()
            ->with('roles:id,name')
            ->select(['id', 'name', 'email', 'is_active', 'must_change_password', 'password_changed_at', 'last_login_at', 'created_at'])
            ->orderBy('name');

        if (request()->filled('search')) {
            $search = (string) request()->string('search');
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (request()->filled('role')) {
            $role = (string) request()->string('role');
            $query->role($role);
        }

        return response()->json($query->limit(request()->integer('limit', 200))->get());
    }

    public function roles()
    {
        return response()->json(Role::query()->orderBy('name')->get(['id', 'name']));
    }

    public function storeUser(Request $request)
    {
        $data = $this->validateUser($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_active' => $data['is_active'] ?? true,
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);
        $user->syncRoles($data['role_names'] ?? []);

        return response()->json($user->fresh('roles:id,name'), 201);
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $this->validateUser($request, $user);

        $updates = [
            'name' => $data['name'],
            'email' => $data['email'],
            'is_active' => $data['is_active'] ?? true,
        ];

        if (! empty($data['password'])) {
            $updates['password'] = $data['password'];
            $updates['must_change_password'] = true;
            $updates['password_changed_at'] = null;
            $user->tokens()->delete();
        }

        $user->update($updates);
        $user->syncRoles($data['role_names'] ?? []);

        return response()->json($user->fresh('roles:id,name'));
    }

    public function destroyUser(Request $request, User $user)
    {
        if ($request->user()?->id === $user->id) {
            abort(422, 'No puedes eliminar tu propia cuenta activa.');
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }

    public function faculties()
    {
        return response()->json(Faculty::with('institution')->orderBy('name')->get());
    }

    public function storeFaculty(Request $request)
    {
        $data = $this->validateFaculty($request);
        $data['institution_id'] = $data['institution_id'] ?? $this->defaultInstitutionId();

        return response()->json(Faculty::create($data)->load('institution'), 201);
    }

    public function updateFaculty(Request $request, Faculty $faculty)
    {
        $data = $this->validateFaculty($request, $faculty);
        $faculty->update($data);

        return response()->json($faculty->fresh('institution'));
    }

    public function destroyFaculty(Faculty $faculty)
    {
        $faculty->delete();

        return response()->json(['message' => 'Facultad eliminada correctamente.']);
    }

    public function programs()
    {
        return response()->json(AcademicProgram::with('faculty')->orderBy('name')->get());
    }

    public function storeProgram(Request $request)
    {
        $data = $this->validateProgram($request);

        return response()->json(AcademicProgram::create($data)->load('faculty'), 201);
    }

    public function updateProgram(Request $request, AcademicProgram $program)
    {
        $data = $this->validateProgram($request, $program);
        $program->update($data);

        return response()->json($program->fresh('faculty'));
    }

    public function destroyProgram(AcademicProgram $program)
    {
        $program->delete();

        return response()->json(['message' => 'Programa eliminado correctamente.']);
    }

    public function studyPlans()
    {
        return response()->json(StudyPlan::with('program')->latest('year')->orderBy('name')->get());
    }

    public function storeStudyPlan(Request $request)
    {
        $data = $this->validateStudyPlan($request);

        return response()->json(StudyPlan::create($data)->load('program'), 201);
    }

    public function updateStudyPlan(Request $request, StudyPlan $studyPlan)
    {
        $data = $this->validateStudyPlan($request, $studyPlan);
        $studyPlan->update($data);

        return response()->json($studyPlan->fresh('program'));
    }

    public function destroyStudyPlan(StudyPlan $studyPlan)
    {
        $studyPlan->delete();

        return response()->json(['message' => 'Plan de estudios eliminado correctamente.']);
    }

    public function courses()
    {
        return response()->json(CurriculumCourse::with('studyPlan.program')->orderBy('name')->get());
    }

    public function storeCourse(Request $request)
    {
        $data = $this->validateCourse($request);

        return response()->json(CurriculumCourse::create($data)->load('studyPlan.program'), 201);
    }

    public function updateCourse(Request $request, CurriculumCourse $course)
    {
        $data = $this->validateCourse($request, $course);
        $course->update($data);

        return response()->json($course->fresh('studyPlan.program'));
    }

    public function destroyCourse(CurriculumCourse $course)
    {
        $course->delete();

        return response()->json(['message' => 'Curso eliminado correctamente.']);
    }

    public function courseOfferings()
    {
        return response()->json(CourseOffering::query()
            ->with(['program', 'term.year', 'course.studyPlan', 'assignments.teacher.user'])
            ->latest('id')
            ->get());
    }

    public function storeCourseOffering(Request $request)
    {
        $data = $this->validateCourseOffering($request);

        return DB::transaction(function () use ($data) {
            $assignment = $this->pullOfferingAssignmentData($data);
            $offering = CourseOffering::create($data);
            $this->syncMainAssignment($offering, $assignment);
            $this->syncCourseOfferingEvidenceTasks($offering);

            return response()->json($offering->fresh(['program', 'term.year', 'course.studyPlan', 'assignments.teacher.user']), 201);
        });
    }

    public function updateCourseOffering(Request $request, CourseOffering $courseOffering)
    {
        $data = $this->validateCourseOffering($request);

        return DB::transaction(function () use ($courseOffering, $data) {
            $assignment = $this->pullOfferingAssignmentData($data);
            $courseOffering->update($data);
            $this->syncMainAssignment($courseOffering, $assignment);
            $this->syncCourseOfferingEvidenceTasks($courseOffering);

            return response()->json($courseOffering->fresh(['program', 'term.year', 'course.studyPlan', 'assignments.teacher.user']));
        });
    }

    public function destroyCourseOffering(CourseOffering $courseOffering)
    {
        $courseOffering->delete();
        EvidenceTask::query()
            ->whereIn('context_type', ['course_offering', 'assessment_course'])
            ->where('context_id', $courseOffering->id)
            ->delete();

        return response()->json(['message' => 'Carga docente eliminada correctamente.']);
    }

    public function teachers()
    {
        return response()->json(Teacher::with(['institution', 'user'])->orderBy('last_name')->orderBy('first_name')->get());
    }

    public function storeTeacher(Request $request)
    {
        $data = $this->validateTeacher($request);
        $data['institution_id'] = $data['institution_id'] ?? $this->defaultInstitutionId();

        return DB::transaction(function () use ($data) {
            $userData = $this->pullTeacherUserData($data);
            if ($userData['create_user']) {
                $user = User::create([
                    'name' => trim($data['first_name'].' '.$data['last_name']),
                    'email' => $data['email'],
                    'password' => $userData['password'] ?: 'password',
                    'is_active' => true,
                    'must_change_password' => true,
                    'password_changed_at' => null,
                ]);
                $user->assignRole('docente');
                $data['user_id'] = $user->id;
            }

            return response()->json(Teacher::create($data)->load(['institution', 'user']), 201);
        });
    }

    public function updateTeacher(Request $request, Teacher $teacher)
    {
        $data = $this->validateTeacher($request, $teacher);
        $this->pullTeacherUserData($data);
        $teacher->update($data);

        return response()->json($teacher->fresh(['institution', 'user']));
    }

    public function destroyTeacher(Teacher $teacher)
    {
        $teacher->delete();

        return response()->json(['message' => 'Docente eliminado correctamente.']);
    }

    public function createTeacherUser(Request $request, Teacher $teacher)
    {
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', 'min:6', 'max:120'],
        ]);

        if ($teacher->user_id) {
            abort(422, 'El docente ya tiene una cuenta asociada.');
        }

        $email = $data['email'] ?? $teacher->email;
        if (! $email) {
            abort(422, 'El docente necesita correo para crear cuenta.');
        }

        $user = User::create([
            'name' => trim($teacher->first_name.' '.$teacher->last_name),
            'email' => $email,
            'password' => Hash::make($data['password'] ?? 'password'),
            'is_active' => true,
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);
        $user->assignRole('docente');
        $teacher->update(['user_id' => $user->id, 'email' => $email]);
        $this->syncTeacherEvidenceTasks($teacher->fresh());

        return response()->json($teacher->fresh(['institution', 'user']), 201);
    }

    public function uploadTeacherCv(Request $request, Teacher $teacher, EvidenceService $evidenceService)
    {
        $maxKb = (int) config('accreditation.max_upload_mb', 100) * 1024;

        $data = $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'accreditation_cycle_id' => ['required', 'exists:accreditation_cycles,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['required', 'file', 'max:'.$maxKb, 'mimes:'.implode(',', config('accreditation.allowed_extensions'))],
        ]);

        $cycle = AccreditationCycle::query()->with('model')->findOrFail($data['accreditation_cycle_id']);
        $requirement = EvidenceRequirement::query()
            ->where('code', 'C6-REG-01')
            ->whereHas('criterion', fn ($query) => $query->where('accreditation_model_id', $cycle->accreditation_model_id))
            ->firstOrFail();

        $task = EvidenceTask::firstOrCreate(
            [
                'accreditation_cycle_id' => $cycle->id,
                'program_id' => $data['program_id'],
                'accreditation_criterion_id' => $requirement->accreditation_criterion_id,
                'evidence_requirement_id' => $requirement->id,
                'context_type' => 'teacher',
                'context_id' => $teacher->id,
            ],
            [
                'accreditation_subcriterion_id' => $requirement->accreditation_subcriterion_id,
                'assigned_to' => $teacher->user_id,
                'created_by' => $request->user()?->id,
                'status' => 'pending',
                'priority' => 'high',
                'instructions' => 'Subir CV docente y documentos de soporte para el criterio C6.',
            ]
        );

        $evidence = $evidenceService->create([
            'program_id' => $data['program_id'],
            'accreditation_cycle_id' => $cycle->id,
            'criterion_id' => $requirement->accreditation_criterion_id,
            'subcriterion_id' => $requirement->accreditation_subcriterion_id,
            'evidence_requirement_id' => $requirement->id,
            'evidence_task_id' => $task->id,
            'teacher_id' => $teacher->id,
            'title' => $data['title'] ?: 'CV docente - '.$teacher->last_name.', '.$teacher->first_name,
            'description' => $data['description'] ?? 'Curriculum vitae y documentos de soporte docente.',
        ], $request->file('file'), $request);

        return response()->json([
            'message' => 'CV docente registrado como evidencia del criterio C6.',
            'data' => $evidence,
        ], 201);
    }

    public function accreditationCriteria()
    {
        return response()->json(AccreditationCriterion::query()
            ->with('accreditationModel:id,code,name')
            ->orderBy('accreditation_model_id')
            ->orderBy('order')
            ->orderBy('code')
            ->get());
    }

    public function storeAccreditationCriterion(Request $request)
    {
        $data = $this->validateAccreditationCriterion($request);

        return response()->json(AccreditationCriterion::create($data)->load('accreditationModel:id,code,name'), 201);
    }

    public function updateAccreditationCriterion(Request $request, AccreditationCriterion $criterion)
    {
        $data = $this->validateAccreditationCriterion($request, $criterion);
        $criterion->update($data);

        return response()->json($criterion->fresh('accreditationModel:id,code,name'));
    }

    public function destroyAccreditationCriterion(AccreditationCriterion $criterion)
    {
        $hasDependencies = EvidenceRequirement::query()
            ->where('accreditation_criterion_id', $criterion->id)
            ->exists()
            || EvidenceTask::query()
                ->where('accreditation_criterion_id', $criterion->id)
                ->exists();

        if ($hasDependencies) {
            $criterion->update(['is_active' => false]);

            return response()->json(['message' => 'El criterio tiene datos asociados y fue desactivado.']);
        }

        $criterion->delete();

        return response()->json(['message' => 'Criterio eliminado correctamente.']);
    }

    public function accreditationSubcriteria()
    {
        return response()->json(AccreditationSubcriterion::query()
            ->with('criterion.accreditationModel:id,code,name')
            ->withCount('requirements')
            ->orderBy('accreditation_criterion_id')
            ->orderBy('order')
            ->orderBy('code')
            ->get());
    }

    public function storeAccreditationSubcriterion(Request $request)
    {
        $data = $this->validateAccreditationSubcriterion($request);

        return response()->json(AccreditationSubcriterion::create($data)->load('criterion.accreditationModel:id,code,name'), 201);
    }

    public function updateAccreditationSubcriterion(Request $request, AccreditationSubcriterion $subcriterion)
    {
        $data = $this->validateAccreditationSubcriterion($request);
        $subcriterion->update($data);

        return response()->json($subcriterion->fresh('criterion.accreditationModel:id,code,name'));
    }

    public function destroyAccreditationSubcriterion(AccreditationSubcriterion $subcriterion)
    {
        $hasDependencies = EvidenceRequirement::query()
            ->where('accreditation_subcriterion_id', $subcriterion->id)
            ->exists()
            || EvidenceTask::query()
                ->where('accreditation_subcriterion_id', $subcriterion->id)
                ->exists();

        if ($hasDependencies) {
            $subcriterion->update(['is_active' => false]);

            return response()->json(['message' => 'El subcriterio tiene datos asociados y fue desactivado.']);
        }

        $subcriterion->delete();

        return response()->json(['message' => 'Subcriterio eliminado correctamente.']);
    }

    public function evidenceRequirements()
    {
        return response()->json(EvidenceRequirement::query()
            ->with(['criterion.accreditationModel:id,code,name', 'subcriterion:id,code,name'])
            ->orderBy('accreditation_criterion_id')
            ->orderBy('order')
            ->orderBy('code')
            ->get());
    }

    public function storeEvidenceRequirement(Request $request)
    {
        $data = $this->validateEvidenceRequirement($request);

        return response()->json(EvidenceRequirement::create($data)->load(['criterion.accreditationModel:id,code,name', 'subcriterion:id,code,name']), 201);
    }

    public function updateEvidenceRequirement(Request $request, EvidenceRequirement $requirement)
    {
        $data = $this->validateEvidenceRequirement($request);
        $requirement->update($data);

        return response()->json($requirement->fresh(['criterion.accreditationModel:id,code,name', 'subcriterion:id,code,name']));
    }

    public function destroyEvidenceRequirement(EvidenceRequirement $requirement)
    {
        $hasDependencies = EvidenceTask::query()
            ->where('evidence_requirement_id', $requirement->id)
            ->exists()
            || DB::table('evidence_submissions')
                ->where('evidence_requirement_id', $requirement->id)
                ->exists();

        if ($hasDependencies) {
            $requirement->update(['is_active' => false]);

            return response()->json(['message' => 'El requerimiento tiene tareas o evidencias asociadas y fue desactivado.']);
        }

        $requirement->delete();

        return response()->json(['message' => 'Requerimiento eliminado correctamente.']);
    }

    private function validateAccreditationCriterion(Request $request, ?AccreditationCriterion $criterion = null): array
    {
        return $request->validate([
            'accreditation_model_id' => ['required', 'exists:accreditation_models,id'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('accreditation_criteria', 'code')
                    ->where(fn ($query) => $query->where('accreditation_model_id', $request->input('accreditation_model_id', $criterion?->accreditation_model_id)))
                    ->ignore($criterion?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ]);
    }

    private function validateAccreditationSubcriterion(Request $request): array
    {
        return $request->validate([
            'accreditation_criterion_id' => ['required', 'exists:accreditation_criteria,id'],
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ]);
    }

    private function validateEvidenceRequirement(Request $request): array
    {
        $data = $request->validate([
            'accreditation_criterion_id' => ['required', 'exists:accreditation_criteria,id'],
            'accreditation_subcriterion_id' => ['nullable', 'exists:accreditation_subcriteria,id'],
            'code' => ['nullable', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'applies_to' => ['required', Rule::in(config('accreditation.context_types'))],
            'evidence_kind' => ['required', 'string', 'max:80'],
            'is_required' => ['boolean'],
            'allows_multiple_files' => ['boolean'],
            'allowed_extensions' => ['nullable', 'array'],
            'allowed_extensions.*' => ['required', 'string', 'max:30'],
            'order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ]);

        if (! empty($data['accreditation_subcriterion_id'])) {
            $belongsToCriterion = AccreditationSubcriterion::query()
                ->where('id', $data['accreditation_subcriterion_id'])
                ->where('accreditation_criterion_id', $data['accreditation_criterion_id'])
                ->exists();

            abort_unless($belongsToCriterion, 422, 'El subcriterio no pertenece al criterio seleccionado.');
        }

        $data['allowed_extensions'] = array_values(array_unique(array_map(
            fn ($extension) => strtolower(trim((string) $extension)),
            $data['allowed_extensions'] ?? config('accreditation.allowed_extensions')
        )));

        return $data;
    }

    private function validateFaculty(Request $request, ?Faculty $faculty = null): array
    {
        return $request->validate([
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('faculties', 'code')
                    ->where(fn ($query) => $query->where('institution_id', $request->input('institution_id', $faculty?->institution_id ?? $this->defaultInstitutionId())))
                    ->ignore($faculty?->id),
            ],
            'is_active' => ['boolean'],
        ]);
    }

    private function validateInstitution(Request $request, ?Institution $institution = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('institutions', 'short_name')->ignore($institution?->id),
            ],
            'ruc' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6', 'max:120'],
            'is_active' => ['boolean'],
            'role_names' => ['required', 'array', 'min:1'],
            'role_names.*' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);
    }

    private function validateProgram(Request $request, ?AcademicProgram $program = null): array
    {
        return $request->validate([
            'faculty_id' => ['required', 'exists:faculties,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('programs', 'code')
                    ->where(fn ($query) => $query->where('faculty_id', $request->input('faculty_id', $program?->faculty_id)))
                    ->ignore($program?->id),
            ],
            'degree_name' => ['nullable', 'string', 'max:255'],
            'professional_title' => ['nullable', 'string', 'max:255'],
            'modality' => ['nullable', 'string', 'max:80'],
            'is_active' => ['boolean'],
        ]);
    }

    private function validateStudyPlan(Request $request, ?StudyPlan $studyPlan = null): array
    {
        return $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('study_plans', 'code')
                    ->where(fn ($query) => $query->where('program_id', $request->input('program_id', $studyPlan?->program_id)))
                    ->ignore($studyPlan?->id),
            ],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'approved_on' => ['nullable', 'date'],
            'approval_document' => ['nullable', 'string', 'max:255'],
            'is_current' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }

    private function validateCourse(Request $request, ?CurriculumCourse $course = null): array
    {
        return $request->validate([
            'study_plan_id' => ['required', 'exists:study_plans,id'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('courses', 'code')
                    ->where(fn ($query) => $query->where('study_plan_id', $request->input('study_plan_id', $course?->study_plan_id)))
                    ->ignore($course?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'cycle_number' => ['nullable', 'integer', 'between:1,20'],
            'credits' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'theory_hours' => ['nullable', 'integer', 'min:0', 'max:99'],
            'practice_hours' => ['nullable', 'integer', 'min:0', 'max:99'],
            'lab_hours' => ['nullable', 'integer', 'min:0', 'max:99'],
            'is_required' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }

    private function validateCourseOffering(Request $request): array
    {
        return $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'academic_term_id' => ['required', 'exists:academic_terms,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'section' => ['nullable', 'string', 'max:50'],
            'group_code' => ['nullable', 'string', 'max:80'],
            'enrolled_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'status' => ['nullable', 'string', 'max:50'],
            'is_assessment_course' => ['boolean'],
            'assessment_result_code' => ['nullable', 'string', 'max:30'],
            'assessment_result_name' => ['nullable', 'string', 'max:255'],
            'requires_assessment_video' => ['boolean'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:99'],
        ]);
    }

    private function pullOfferingAssignmentData(array &$data): array
    {
        $assignment = [
            'teacher_id' => $data['teacher_id'] ?? null,
            'weekly_hours' => $data['weekly_hours'] ?? null,
        ];

        unset($data['teacher_id'], $data['weekly_hours']);
        $data['status'] = $data['status'] ?? 'active';
        $data['enrolled_count'] = $data['enrolled_count'] ?? 0;

        return $assignment;
    }

    private function syncMainAssignment(CourseOffering $offering, array $assignment): void
    {
        if (empty($assignment['teacher_id'])) {
            CourseAssignment::where('course_offering_id', $offering->id)
                ->where('role', 'main')
                ->delete();

            return;
        }

        CourseAssignment::where('course_offering_id', $offering->id)
            ->where('role', 'main')
            ->where('teacher_id', '!=', $assignment['teacher_id'])
            ->delete();

        CourseAssignment::updateOrCreate(
            ['course_offering_id' => $offering->id, 'teacher_id' => $assignment['teacher_id'], 'role' => 'main'],
            ['weekly_hours' => $assignment['weekly_hours']]
        );
    }

    private function syncCourseOfferingEvidenceTasks(CourseOffering $offering): void
    {
        $offering->loadMissing(['mainAssignment.teacher.user']);
        $teacher = $offering->mainAssignment?->teacher;
        $assignedTo = $teacher?->user_id;

        $this->pruneAssessmentTasks($offering);

        $cycles = AccreditationCycle::query()
            ->where('program_id', $offering->program_id)
            ->whereIn('status', ['planning', 'active'])
            ->where(function ($query) use ($offering) {
                $query->whereNull('academic_term_id')
                    ->orWhere('academic_term_id', $offering->academic_term_id);
            })
            ->get();

        foreach ($cycles as $cycle) {
            $requirements = EvidenceRequirement::query()
                ->with('criterion')
                ->whereIn('applies_to', ['course_offering', 'assessment_course'])
                ->where('is_active', true)
                ->whereHas('criterion', fn ($query) => $query->where('accreditation_model_id', $cycle->accreditation_model_id))
                ->get();

            foreach ($requirements as $requirement) {
                if ($requirement->applies_to === 'course_offering' && $offering->is_assessment_course) {
                    continue;
                }

                if ($requirement->applies_to === 'assessment_course' && ! $offering->is_assessment_course) {
                    continue;
                }

                if ($requirement->code === 'C3-ASS-04' && ! $offering->requires_assessment_video) {
                    continue;
                }

                $contextType = $requirement->applies_to === 'assessment_course' ? 'assessment_course' : 'course_offering';

                $task = EvidenceTask::firstOrNew([
                    'accreditation_cycle_id' => $cycle->id,
                    'evidence_requirement_id' => $requirement->id,
                    'context_type' => $contextType,
                    'context_id' => $offering->id,
                ]);
                $isNew = ! $task->exists;

                $assessmentLabel = trim(($offering->assessment_result_code ?: 'Assessment').' '.$offering->assessment_result_name);

                $task->fill([
                    'program_id' => $offering->program_id,
                    'accreditation_criterion_id' => $requirement->accreditation_criterion_id,
                    'accreditation_subcriterion_id' => $requirement->accreditation_subcriterion_id,
                    'academic_term_id' => $offering->academic_term_id,
                    'assigned_to' => $assignedTo,
                    'priority' => $requirement->is_required ? 'high' : 'normal',
                    'instructions' => $contextType === 'assessment_course'
                        ? 'Assessment '.$assessmentLabel.': '.$requirement->name.'.'
                        : 'Portafolio de curso: '.$requirement->name.'.',
                    'metadata' => $contextType === 'assessment_course' ? [
                        'assessment_result_code' => $offering->assessment_result_code,
                        'assessment_result_name' => $offering->assessment_result_name,
                        'requires_video' => $offering->requires_assessment_video,
                    ] : null,
                ]);

                if ($isNew) {
                    $task->status = 'pending';
                }

                $task->save();
            }
        }

        if ($teacher) {
            $this->syncTeacherEvidenceTasks($teacher, $offering->program_id);
        }
    }

    private function pruneAssessmentTasks(CourseOffering $offering): void
    {
        if (! $offering->is_assessment_course) {
            EvidenceTask::query()
                ->where('context_type', 'assessment_course')
                ->where('context_id', $offering->id)
                ->delete();

            return;
        }

        EvidenceTask::query()
            ->where('context_type', 'course_offering')
            ->where('context_id', $offering->id)
            ->delete();

        if ($offering->requires_assessment_video) {
            return;
        }

        $videoRequirementIds = EvidenceRequirement::query()
            ->where('code', 'C3-ASS-04')
            ->pluck('id');

        EvidenceTask::query()
            ->where('context_type', 'assessment_course')
            ->where('context_id', $offering->id)
            ->whereIn('evidence_requirement_id', $videoRequirementIds)
            ->delete();
    }

    private function syncTeacherEvidenceTasks(Teacher $teacher, ?int $programId = null): void
    {
        $programIds = $programId
            ? collect([$programId])
            : CourseOffering::query()
                ->whereHas('assignments', fn ($query) => $query->where('teacher_id', $teacher->id))
                ->pluck('program_id')
                ->unique()
                ->values();

        if ($programIds->isEmpty()) {
            return;
        }

        $cycles = AccreditationCycle::query()
            ->whereIn('program_id', $programIds)
            ->whereIn('status', ['planning', 'active'])
            ->get();

        foreach ($cycles as $cycle) {
            $requirements = EvidenceRequirement::query()
                ->with('criterion')
                ->where('applies_to', 'teacher')
                ->where('is_active', true)
                ->whereHas('criterion', fn ($query) => $query->where('accreditation_model_id', $cycle->accreditation_model_id))
                ->get();

            foreach ($requirements as $requirement) {
                $task = EvidenceTask::firstOrNew([
                    'accreditation_cycle_id' => $cycle->id,
                    'evidence_requirement_id' => $requirement->id,
                    'context_type' => 'teacher',
                    'context_id' => $teacher->id,
                ]);
                $isNew = ! $task->exists;

                $task->fill([
                    'program_id' => $cycle->program_id,
                    'accreditation_criterion_id' => $requirement->accreditation_criterion_id,
                    'accreditation_subcriterion_id' => $requirement->accreditation_subcriterion_id,
                    'assigned_to' => $teacher->user_id,
                    'priority' => $requirement->is_required ? 'high' : 'normal',
                    'instructions' => 'Evidencia docente asociada al criterio '.$requirement->criterion->code.'.',
                ]);

                if ($isNew) {
                    $task->status = 'pending';
                }

                $task->save();
            }
        }
    }

    private function validateTeacher(Request $request, ?Teacher $teacher = null): array
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'document_type' => ['nullable', 'string', 'max:20'],
            'document_number' => ['nullable', 'string', 'max:30'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'highest_degree' => ['nullable', 'string', 'max:120'],
            'specialty' => ['nullable', 'string', 'max:180'],
            'employment_type' => ['nullable', 'string', 'max:80'],
            'is_active' => ['boolean'],
            'create_user' => ['boolean'],
            'password' => ['nullable', 'string', 'min:6', 'max:120'],
        ]);

        if (($data['create_user'] ?? false) && empty($data['email'])) {
            abort(422, 'El correo es obligatorio para crear usuario del docente.');
        }

        if (($data['create_user'] ?? false) && User::where('email', $data['email'])->exists()) {
            abort(422, 'Ya existe un usuario con ese correo.');
        }

        return $data;
    }

    private function pullTeacherUserData(array &$data): array
    {
        $userData = [
            'create_user' => (bool) ($data['create_user'] ?? false),
            'password' => $data['password'] ?? null,
        ];

        unset($data['create_user'], $data['password']);

        return $userData;
    }

    private function defaultInstitutionId(): int
    {
        return (int) Institution::query()->value('id');
    }
}
