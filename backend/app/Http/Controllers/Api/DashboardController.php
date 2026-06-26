<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvidenceTask;
use App\Support\AccessScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $query = $this->taskQuery($request);

        $total = (clone $query)->count();
        $byStatus = (clone $query)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $validated = (int) ($byStatus['validated'] ?? 0);
        $approved = (int) ($byStatus['approved'] ?? 0);
        $ready = (int) ($byStatus['ready_to_export'] ?? 0);
        $completed = $validated + $approved + $ready;

        return response()->json([
            'total' => $total,
            'pending' => (int) (($byStatus['pending'] ?? 0) + ($byStatus['assigned'] ?? 0)),
            'uploaded' => (int) ($byStatus['uploaded'] ?? 0),
            'in_review' => (int) ($byStatus['in_review'] ?? 0),
            'observed' => (int) ($byStatus['observed'] ?? 0),
            'corrected' => (int) ($byStatus['corrected'] ?? 0),
            'validated' => $validated,
            'approved' => $approved,
            'ready_to_export' => $ready,
            'validated_or_more' => $completed,
            'progress' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'by_status' => $byStatus,
        ]);
    }

    public function progressByCriterion(Request $request)
    {
        $query = $this->taskQuery($request)
            ->join('accreditation_criteria', 'evidence_tasks.accreditation_criterion_id', '=', 'accreditation_criteria.id')
            ->select(
                'accreditation_criteria.id',
                'accreditation_criteria.code',
                'accreditation_criteria.name',
                DB::raw('COUNT(evidence_tasks.id) as total'),
                DB::raw("SUM(CASE WHEN evidence_tasks.status IN ('validated','approved','ready_to_export') THEN 1 ELSE 0 END) as completed")
            )
            ->groupBy('accreditation_criteria.id', 'accreditation_criteria.code', 'accreditation_criteria.name', 'accreditation_criteria.order')
            ->orderBy('accreditation_criteria.order');

        return response()->json($query->get()->map(function ($item) {
            $item->progress = $item->total > 0 ? round(($item->completed / $item->total) * 100, 2) : 0;
            return $item;
        }));
    }

    public function progressByProgram(Request $request)
    {
        $query = $this->taskQuery($request)
            ->join('programs', 'evidence_tasks.program_id', '=', 'programs.id')
            ->select(
                'programs.id',
                'programs.code',
                'programs.name',
                DB::raw('COUNT(evidence_tasks.id) as total'),
                DB::raw("SUM(CASE WHEN evidence_tasks.status IN ('validated','approved','ready_to_export') THEN 1 ELSE 0 END) as completed")
            )
            ->groupBy('programs.id', 'programs.code', 'programs.name')
            ->orderBy('programs.name');

        return response()->json($query->get()->map(function ($item) {
            $item->progress = $item->total > 0 ? round(($item->completed / $item->total) * 100, 2) : 0;
            return $item;
        }));
    }

    public function pendingByTeacher(Request $request)
    {
        $query = $this->taskQuery($request)
            ->leftJoin('users', 'evidence_tasks.assigned_to', '=', 'users.id')
            ->select(
                'users.id',
                DB::raw("COALESCE(users.name, 'Sin responsable') as name"),
                DB::raw('COUNT(evidence_tasks.id) as total')
            )
            ->whereIn('evidence_tasks.status', ['pending', 'assigned', 'observed'])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total');

        return response()->json($query->limit(20)->get());
    }

    public function teacherEvidenceStatus(Request $request)
    {
        $rows = $this->taskQuery($request)
            ->leftJoin('users', 'evidence_tasks.assigned_to', '=', 'users.id')
            ->leftJoin('teachers', 'teachers.user_id', '=', 'users.id')
            ->leftJoin('evidence_submissions', function ($join) {
                $join->on('evidence_submissions.evidence_task_id', '=', 'evidence_tasks.id')
                    ->whereNull('evidence_submissions.deleted_at');
            })
            ->whereNotNull('evidence_tasks.assigned_to')
            ->select(
                'users.id as user_id',
                'users.name',
                'users.email',
                'teachers.id as teacher_id',
                DB::raw('COUNT(DISTINCT evidence_tasks.id) as total'),
                DB::raw("COUNT(DISTINCT CASE WHEN evidence_tasks.status IN ('pending','assigned') THEN evidence_tasks.id END) as pending"),
                DB::raw("COUNT(DISTINCT CASE WHEN evidence_tasks.status = 'observed' THEN evidence_tasks.id END) as observed"),
                DB::raw("COUNT(DISTINCT CASE WHEN evidence_tasks.status IN ('uploaded','in_review','corrected') THEN evidence_tasks.id END) as uploaded"),
                DB::raw("COUNT(DISTINCT CASE WHEN evidence_tasks.status IN ('validated','approved','ready_to_export') THEN evidence_tasks.id END) as accepted"),
                DB::raw('COUNT(DISTINCT CASE WHEN evidence_submissions.id IS NOT NULL THEN evidence_tasks.id END) as submitted'),
                DB::raw('MAX(evidence_submissions.submitted_at) as last_submission_at')
            )
            ->groupBy('users.id', 'users.name', 'users.email', 'teachers.id')
            ->orderByDesc('pending')
            ->orderBy('users.name')
            ->get();

        return response()->json($rows->map(function ($row) {
            $row->total = (int) $row->total;
            $row->pending = (int) $row->pending;
            $row->observed = (int) $row->observed;
            $row->uploaded = (int) $row->uploaded;
            $row->accepted = (int) $row->accepted;
            $row->submitted = (int) $row->submitted;
            $row->missing = $row->pending;
            $row->progress = $row->total > 0 ? round(($row->submitted / $row->total) * 100, 2) : 0;

            return $row;
        }));
    }

    private function taskQuery(Request $request)
    {
        $query = EvidenceTask::query();
        AccessScope::applyTaskVisibility($query, $request->user());

        if ($request->filled('cycle_id')) {
            $query->where('accreditation_cycle_id', $request->integer('cycle_id'));
        }

        if ($request->filled('accreditation_cycle_id')) {
            $query->where('accreditation_cycle_id', $request->integer('accreditation_cycle_id'));
        }

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->integer('program_id'));
        }

        return $query;
    }
}
