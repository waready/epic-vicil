<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EvidenceTaskResource;
use App\Models\CourseAssignment;
use App\Models\EvidenceTask;
use App\Models\Teacher;
use Illuminate\Http\Request;

class MyEvidenceController extends Controller
{
    public function tasks(Request $request)
    {
        $user = $request->user();
        $teacher = Teacher::where('user_id', $user->id)->first();
        $courseOfferingIds = $teacher
            ? CourseAssignment::where('teacher_id', $teacher->id)->whereHas('courseOffering')->pluck('course_offering_id')
            : collect();

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
            ])
            ->where(function ($inner) use ($user, $teacher, $courseOfferingIds) {
                $inner->where('assigned_to', $user->id);

                if ($teacher) {
                    $inner->orWhere(function ($teacherQuery) use ($teacher) {
                        $teacherQuery->where('context_type', 'teacher')
                            ->where('context_id', $teacher->id);
                    });
                }

                if ($courseOfferingIds->isNotEmpty()) {
                    $inner->orWhere(function ($courseQuery) use ($courseOfferingIds) {
                        $courseQuery->whereIn('context_type', ['course_offering', 'assessment_course'])
                            ->whereIn('context_id', $courseOfferingIds);
                    });
                }
            })
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
