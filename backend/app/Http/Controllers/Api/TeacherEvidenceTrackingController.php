<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EvidenceTaskResource;
use App\Mail\TeacherEvidenceStatusMail;
use App\Models\AccreditationCycle;
use App\Models\EvidenceTask;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class TeacherEvidenceTrackingController extends Controller
{
    public function summary(Request $request)
    {
        $data = $request->validate([
            'cycle_id' => ['nullable', 'integer', 'exists:accreditation_cycles,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $submissionStats = DB::table('evidence_submissions')
            ->whereNull('deleted_at')
            ->select(
                'evidence_task_id',
                DB::raw('MAX(submitted_at) as last_submission_at')
            )
            ->groupBy('evidence_task_id');

        $query = EvidenceTask::query()
            ->join('users', 'evidence_tasks.assigned_to', '=', 'users.id')
            ->leftJoin('teachers', function ($join) {
                $join->on('teachers.user_id', '=', 'users.id')
                    ->whereNull('teachers.deleted_at');
            })
            ->leftJoinSub($submissionStats, 'submission_stats', function ($join) {
                $join->on('submission_stats.evidence_task_id', '=', 'evidence_tasks.id');
            })
            ->whereNotNull('evidence_tasks.assigned_to')
            ->whereNotNull('teachers.id')
            ->whereNull('users.deleted_at')
            ->when($data['cycle_id'] ?? null, fn ($builder, $cycleId) => $builder->where('evidence_tasks.accreditation_cycle_id', $cycleId))
            ->when($data['program_id'] ?? null, fn ($builder, $programId) => $builder->where('evidence_tasks.program_id', $programId))
            ->when($data['search'] ?? null, function ($builder, $search) {
                $term = '%'.trim($search).'%';
                $builder->where(function ($nested) use ($term) {
                    $nested->where('users.name', 'like', $term)
                        ->orWhere('users.email', 'like', $term)
                        ->orWhere('teachers.first_name', 'like', $term)
                        ->orWhere('teachers.last_name', 'like', $term);
                });
            })
            ->select(
                'users.id as user_id',
                'users.name',
                'users.email',
                'teachers.id as teacher_id',
                DB::raw('COUNT(evidence_tasks.id) as total'),
                DB::raw("SUM(CASE WHEN evidence_tasks.status IN ('pending','assigned') THEN 1 ELSE 0 END) as pending"),
                DB::raw("SUM(CASE WHEN evidence_tasks.status = 'observed' THEN 1 ELSE 0 END) as observed"),
                DB::raw("SUM(CASE WHEN evidence_tasks.status IN ('uploaded','in_review','corrected') THEN 1 ELSE 0 END) as uploaded"),
                DB::raw("SUM(CASE WHEN evidence_tasks.status IN ('validated','approved','ready_to_export') THEN 1 ELSE 0 END) as accepted"),
                DB::raw('SUM(CASE WHEN submission_stats.evidence_task_id IS NOT NULL THEN 1 ELSE 0 END) as submitted'),
                DB::raw('SUM(CASE WHEN submission_stats.evidence_task_id IS NULL THEN 1 ELSE 0 END) as missing'),
                DB::raw('MAX(submission_stats.last_submission_at) as last_submission_at')
            )
            ->groupBy('users.id', 'users.name', 'users.email', 'teachers.id')
            ->orderByDesc('missing')
            ->orderBy('users.name');

        $paginator = $query->paginate($data['per_page'] ?? 15);
        $paginator->through(function ($row) {
            foreach (['total', 'pending', 'observed', 'uploaded', 'accepted', 'submitted', 'missing'] as $field) {
                $row->{$field} = (int) $row->{$field};
            }

            $row->progress = $row->total > 0
                ? round(($row->submitted / $row->total) * 100, 2)
                : 0;

            return $row;
        });

        return response()->json($paginator);
    }

    public function tasks(Request $request, User $user)
    {
        abort_unless($user->teacher()->exists(), 404, 'El usuario seleccionado no corresponde a un docente.');

        $data = $request->validate([
            'cycle_id' => ['nullable', 'integer', 'exists:accreditation_cycles,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'scope' => ['nullable', Rule::in(['all', 'missing', 'submitted', 'observed', 'accepted'])],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = EvidenceTask::query()
            ->where('assigned_to', $user->id)
            ->with([
                'cycle.model',
                'cycle.term',
                'program',
                'criterion',
                'subcriterion',
                'requirement',
                'courseOfferingContext.course',
                'courseOfferingContext.term',
                'teacherContext',
                'currentSubmission.currentFile',
                'submissions' => fn ($builder) => $builder
                    ->with(['currentFile', 'submittedBy:id,name'])
                    ->latest('submitted_at'),
            ])
            ->when($data['cycle_id'] ?? null, fn ($builder, $cycleId) => $builder->where('accreditation_cycle_id', $cycleId))
            ->when($data['program_id'] ?? null, fn ($builder, $programId) => $builder->where('program_id', $programId));

        $scope = $data['scope'] ?? 'all';
        if ($scope === 'missing') {
            $query->whereDoesntHave('submissions');
        } elseif ($scope === 'submitted') {
            $query->whereHas('submissions');
        } elseif ($scope === 'observed') {
            $query->where('status', 'observed');
        } elseif ($scope === 'accepted') {
            $query->whereIn('status', ['validated', 'approved', 'ready_to_export']);
        }

        if (! empty($data['search'])) {
            $term = '%'.trim($data['search']).'%';
            $query->where(function ($nested) use ($term) {
                $nested->whereHas('requirement', fn ($requirement) => $requirement
                    ->where(fn ($fields) => $fields
                        ->where('code', 'like', $term)
                        ->orWhere('name', 'like', $term)))
                    ->orWhereHas('courseOfferingContext.course', fn ($course) => $course
                        ->where(fn ($fields) => $fields
                            ->where('code', 'like', $term)
                            ->orWhere('name', 'like', $term)));
            });
        }

        $tasks = $query
            ->orderBy('accreditation_criterion_id')
            ->orderBy('context_type')
            ->orderBy('context_id')
            ->orderBy('evidence_requirement_id')
            ->paginate($data['per_page'] ?? 15);

        return EvidenceTaskResource::collection($tasks)->additional([
            'teacher' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'teacher_id' => $user->teacher()->value('id'),
            ],
        ]);
    }

    public function sendStatusEmails(Request $request)
    {
        $data = $request->validate([
            'cycle_id' => ['nullable', 'integer', 'exists:accreditation_cycles,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'only_missing' => ['boolean'],
        ]);

        $onlyMissing = $data['only_missing'] ?? true;
        $taskUserIds = EvidenceTask::query()
            ->whereNotNull('assigned_to')
            ->when($data['cycle_id'] ?? null, fn ($builder, $cycleId) => $builder->where('accreditation_cycle_id', $cycleId))
            ->when($data['program_id'] ?? null, fn ($builder, $programId) => $builder->where('program_id', $programId))
            ->when($data['user_ids'] ?? null, fn ($builder, $ids) => $builder->whereIn('assigned_to', $ids))
            ->pluck('assigned_to')
            ->unique()
            ->values();

        $users = User::query()
            ->with('teacher')
            ->whereIn('id', $taskUserIds)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->orderBy('name')
            ->get();

        $sent = [];
        $skipped = [];
        $failed = [];

        foreach ($users as $user) {
            if (! $user->teacher) {
                $skipped[] = ['email' => $user->email, 'reason' => 'No es docente.'];
                continue;
            }

            $summary = $this->teacherMailSummary($user, $data);
            if ($onlyMissing && $summary['missing'] === 0) {
                $skipped[] = ['email' => $user->email, 'reason' => 'Sin evidencias faltantes.'];
                continue;
            }

            $missingTasks = $this->teacherMissingTasks($user, $data);

            try {
                Mail::to($user->email)->send(new TeacherEvidenceStatusMail($summary, $missingTasks));
                $sent[] = ['email' => $user->email, 'missing' => $summary['missing']];
            } catch (\Throwable $exception) {
                $failed[] = ['email' => $user->email, 'error' => $exception->getMessage()];
            }
        }

        return response()->json([
            'message' => 'Proceso de envio de correos finalizado.',
            'summary' => [
                'sent' => count($sent),
                'skipped' => count($skipped),
                'failed' => count($failed),
            ],
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);
    }

    private function teacherMailSummary(User $user, array $filters): array
    {
        $base = $this->teacherTaskQuery($user, $filters);
        $total = (clone $base)->count();
        $submitted = (clone $base)->whereHas('submissions')->count();
        $missing = max($total - $submitted, 0);
        $observed = (clone $base)->where('status', 'observed')->count();
        $accepted = (clone $base)->whereIn('status', ['validated', 'approved', 'ready_to_export'])->count();
        $cycle = $this->mailCycleLabel($filters['cycle_id'] ?? null);
        $program = (clone $base)->with('program:id,name')->first()?->program?->name ?: 'Programa academico';

        return [
            'teacher' => $user->name,
            'email' => $user->email,
            'cycle' => $cycle,
            'program' => $program,
            'total' => $total,
            'submitted' => $submitted,
            'missing' => $missing,
            'observed' => $observed,
            'accepted' => $accepted,
            'progress' => $total > 0 ? round(($submitted / $total) * 100, 2) : 0,
        ];
    }

    private function teacherMissingTasks(User $user, array $filters): array
    {
        return $this->teacherTaskQuery($user, $filters)
            ->whereDoesntHave('submissions')
            ->with([
                'cycle.model',
                'cycle.term',
                'criterion',
                'subcriterion',
                'requirement',
                'courseOfferingContext.course',
                'courseOfferingContext.term',
                'teacherContext',
            ])
            ->orderBy('accreditation_criterion_id')
            ->orderBy('context_type')
            ->orderBy('context_id')
            ->orderBy('evidence_requirement_id')
            ->get()
            ->map(fn (EvidenceTask $task) => [
                'context' => $this->taskContextLabel($task),
                'criterion' => trim(($task->criterion?->code ?: '').' - '.($task->criterion?->name ?: '')),
                'subcriterion' => trim(($task->subcriterion?->code ?: '').' - '.($task->subcriterion?->name ?: '')),
                'code' => $task->requirement?->code ?: 'Sin codigo',
                'name' => $task->requirement?->name ?: 'Sin requerimiento',
            ])
            ->values()
            ->all();
    }

    private function teacherTaskQuery(User $user, array $filters)
    {
        return EvidenceTask::query()
            ->where('assigned_to', $user->id)
            ->when($filters['cycle_id'] ?? null, fn ($builder, $cycleId) => $builder->where('accreditation_cycle_id', $cycleId))
            ->when($filters['program_id'] ?? null, fn ($builder, $programId) => $builder->where('program_id', $programId));
    }

    private function mailCycleLabel(?int $cycleId): string
    {
        $cycle = $cycleId
            ? AccreditationCycle::query()->with(['model:id,code', 'term:id,code'])->find($cycleId)
            : AccreditationCycle::query()->with(['model:id,code', 'term:id,code'])->where('status', 'active')->first();

        return trim(($cycle?->model?->code ?: 'ICACIT').' '.($cycle?->term?->code ?: $cycle?->name));
    }

    private function taskContextLabel(EvidenceTask $task): string
    {
        if (in_array($task->context_type, ['course_offering', 'assessment_course'], true) && $task->courseOfferingContext) {
            $course = $task->courseOfferingContext->course;
            $term = $task->courseOfferingContext->term;
            $assessment = $task->context_type === 'assessment_course'
                ? trim(($task->courseOfferingContext->assessment_result_code ?: '').' '.$task->courseOfferingContext->assessment_result_name)
                : '';
            $group = $task->context_type === 'assessment_course' && $task->courseOfferingContext->section
                ? 'Grupo '.$task->courseOfferingContext->section
                : $task->courseOfferingContext->section;

            return implode(' / ', array_filter([
                $course ? $course->code.' - '.$course->name : 'Curso',
                $term?->code,
                $group,
                $assessment,
            ]));
        }

        if ($task->context_type === 'teacher' && $task->teacherContext) {
            return trim($task->teacherContext->last_name.', '.$task->teacherContext->first_name);
        }

        return 'Evidencia institucional';
    }
}
