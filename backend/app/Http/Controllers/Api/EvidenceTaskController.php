<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EvidenceTaskResource;
use App\Models\EvidenceStatusHistory;
use App\Models\EvidenceTask;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class EvidenceTaskController extends Controller
{
    public function index(Request $request)
    {
        $query = EvidenceTask::query()
            ->with(['cycle.model', 'cycle.term', 'program', 'criterion', 'subcriterion', 'requirement', 'assignee', 'currentSubmission.currentFile'])
            ->with(['courseOfferingContext.course', 'courseOfferingContext.term', 'teacherContext'])
            ->latest();

        AccessScope::applyTaskVisibility($query, $request->user());

        foreach ([
            'cycle_id' => 'accreditation_cycle_id',
            'accreditation_cycle_id' => 'accreditation_cycle_id',
            'program_id' => 'program_id',
            'criterion_id' => 'accreditation_criterion_id',
            'requirement_id' => 'evidence_requirement_id',
            'evidence_requirement_id' => 'evidence_requirement_id',
            'assigned_to' => 'assigned_to',
            'status' => 'status',
        ] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->input($input));
            }
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->whereHas('requirement', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        return EvidenceTaskResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    public function show(EvidenceTask $evidenceTask)
    {
        abort_unless(AccessScope::taskIsVisible($evidenceTask, request()->user()), 403, 'No puedes ver esta tarea.');

        return new EvidenceTaskResource($evidenceTask->load([
            'cycle.model', 'cycle.term', 'program.faculty', 'criterion', 'subcriterion', 'requirement', 'assignee',
            'courseOfferingContext.course', 'courseOfferingContext.term', 'teacherContext',
            'submissions.currentFile', 'submissions.versions.file', 'submissions.reviews.reviewer', 'histories'
        ]));
    }

    public function updateStatus(Request $request, EvidenceTask $evidenceTask)
    {
        abort_unless($request->user()?->hasAnyPermission(['manage.accreditation', 'review.evidences', 'validate.evidences', 'approve.evidences']), 403);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', config('accreditation.statuses'))],
            'comment' => ['nullable', 'string'],
        ]);

        $from = $evidenceTask->status;
        $evidenceTask->update(['status' => $data['status']]);

        EvidenceStatusHistory::create([
            'evidence_task_id' => $evidenceTask->id,
            'changed_by' => $request->user()?->id,
            'from_status' => $from,
            'to_status' => $data['status'],
            'comment' => $data['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $evidenceTask->fresh(),
        ]);
    }

    public function assign(Request $request, EvidenceTask $evidenceTask)
    {
        abort_unless($request->user()?->hasAnyPermission(['manage.accreditation', 'manage.catalogs']), 403);

        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        $evidenceTask->update([
            'assigned_to' => $data['assigned_to'],
            'due_date' => $data['due_date'] ?? $evidenceTask->due_date,
            'status' => 'assigned',
        ]);

        return response()->json([
            'message' => 'Responsable asignado correctamente.',
            'data' => $evidenceTask->fresh('assignee'),
        ]);
    }
}
