<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EvidenceTaskResource;
use App\Models\EvidenceTask;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class MyEvidenceController extends Controller
{
    public function tasks(Request $request)
    {
        $user = $request->user();

        $query = EvidenceTask::query()
            ->with([
                'cycle.model',
                'cycle.term',
                'program',
                'criterion',
                'subcriterion',
                'requirement',
                'assignee',
                'currentSubmission.currentFile',
                'courseOfferingContext.course',
                'courseOfferingContext.term',
                'teacherContext',
            ]);

        AccessScope::applyTaskVisibility($query, $user);

        $query
            ->where(function ($contextQuery) {
                $contextQuery->whereNull('context_type')
                    ->orWhereNotIn('context_type', ['course_offering', 'assessment_course'])
                    ->orWhereHas('courseOfferingContext');
            })
            ->orderBy('accreditation_criterion_id')
            ->orderBy('evidence_requirement_id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return EvidenceTaskResource::collection($query->paginate($request->integer('per_page', 50)));
    }
}
