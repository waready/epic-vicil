<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicProgram;
use App\Models\AcademicTerm;
use App\Models\AccreditationCycle;
use App\Models\AccreditationCriterion;
use App\Models\AccreditationModel;
use App\Models\CurriculumCourse;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceTask;
use App\Models\Faculty;
use App\Models\StudyPlan;
use App\Models\Teacher;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function faculties()
    {
        return response()->json(Faculty::query()
            ->select(['id', 'institution_id', 'code', 'name'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get());
    }

    public function programs(Request $request)
    {
        $query = AcademicProgram::query()
            ->select(['id', 'faculty_id', 'code', 'name', 'degree_name', 'professional_title', 'modality'])
            ->with('faculty:id,code,name')
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->filled('faculty_id')) {
            $query->where('faculty_id', $request->integer('faculty_id'));
        }

        return response()->json($query->get());
    }

    public function accreditationModels()
    {
        return response()->json(AccreditationModel::query()->where('is_active', true)->orderBy('name')->get());
    }

    public function accreditationCycles(Request $request)
    {
        $query = AccreditationCycle::query()
            ->select(['id', 'accreditation_model_id', 'program_id', 'academic_term_id', 'year', 'name', 'status', 'starts_on', 'ends_on'])
            ->with(['model:id,code,name', 'program:id,code,name', 'term:id,academic_year_id,code,name'])
            ->latest('year');

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->integer('program_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->get());
    }

    public function criteria(Request $request)
    {
        $query = AccreditationCriterion::query()
            ->select(['id', 'accreditation_model_id', 'code', 'name', 'description', 'order'])
            ->with('subcriteria:id,accreditation_criterion_id,code,name,order')
            ->where('is_active', true)
            ->orderBy('order');

        if ($request->filled('model_id')) {
            $query->where('accreditation_model_id', $request->integer('model_id'));
        }

        return response()->json($query->get());
    }

    public function evidenceRequirements(Request $request)
    {
        $query = EvidenceRequirement::query()
            ->select([
                'id',
                'accreditation_criterion_id',
                'accreditation_subcriterion_id',
                'code',
                'name',
                'applies_to',
                'evidence_kind',
                'is_required',
                'allows_multiple_files',
                'order',
            ])
            ->with(['criterion:id,code,name', 'subcriterion:id,code,name'])
            ->where('is_active', true)
            ->orderBy('order');

        if ($request->filled('criterion_id')) {
            $query->where('accreditation_criterion_id', $request->integer('criterion_id'));
        }

        return response()->json($query->get());
    }

    public function studyPlans(Request $request)
    {
        $query = StudyPlan::query()
            ->select(['id', 'program_id', 'code', 'name', 'year', 'is_current'])
            ->with('program:id,code,name')
            ->where('is_active', true)
            ->latest('year');

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->integer('program_id'));
        }

        return response()->json($query->get());
    }

    public function semesters()
    {
        return response()->json(AcademicTerm::query()
            ->select(['id', 'academic_year_id', 'code', 'name'])
            ->with('year:id,year,name')
            ->where('is_active', true)
            ->latest('id')
            ->get());
    }

    public function courses(Request $request)
    {
        $query = CurriculumCourse::query()
            ->select(['id', 'study_plan_id', 'code', 'name', 'cycle_number', 'credits'])
            ->with('studyPlan:id,program_id,code,name')
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->filled('study_plan_id')) {
            $query->where('study_plan_id', $request->integer('study_plan_id'));
        }

        if ($request->filled('program_id')) {
            $query->whereHas('studyPlan', fn ($studyPlan) => $studyPlan->where('program_id', $request->integer('program_id')));
        }

        return response()->json($query->get());
    }

    public function teachers(Request $request)
    {
        $query = Teacher::query()
            ->select(['id', 'user_id', 'first_name', 'last_name', 'email', 'highest_degree', 'specialty'])
            ->with('user:id,name,email')
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($inner) use ($search) {
                $inner->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json($query->get());
    }

    public function evidenceTasks(Request $request)
    {
        $query = EvidenceTask::query()
            ->select([
                'id',
                'accreditation_cycle_id',
                'program_id',
                'accreditation_criterion_id',
                'accreditation_subcriterion_id',
                'evidence_requirement_id',
                'academic_term_id',
                'context_type',
                'context_id',
                'assigned_to',
                'status',
                'priority',
                'due_date',
                'created_at',
            ])
            ->with([
                'cycle:id,accreditation_model_id,program_id,academic_term_id,name,year,status',
                'cycle.term:id,academic_year_id,code,name',
                'cycle.model:id,code,name',
                'program:id,code,name',
                'criterion:id,code,name,order',
                'subcriterion:id,code,name',
                'requirement:id,accreditation_criterion_id,accreditation_subcriterion_id,code,name,applies_to,evidence_kind',
                'assignee:id,name,email',
                'courseOfferingContext:id,program_id,academic_term_id,course_id,section,group_code,is_assessment_course,assessment_result_code,assessment_result_name,requires_assessment_video',
                'courseOfferingContext.course:id,study_plan_id,code,name',
                'courseOfferingContext.term:id,academic_year_id,code,name',
            ])
            ->orderBy('accreditation_criterion_id')
            ->orderBy('evidence_requirement_id');

        AccessScope::applyTaskVisibility($query, $request->user());

        foreach ([
            'cycle_id' => 'accreditation_cycle_id',
            'accreditation_cycle_id' => 'accreditation_cycle_id',
            'program_id' => 'program_id',
            'criterion_id' => 'accreditation_criterion_id',
            'subcriterion_id' => 'accreditation_subcriterion_id',
            'accreditation_subcriterion_id' => 'accreditation_subcriterion_id',
            'evidence_requirement_id' => 'evidence_requirement_id',
            'status' => 'status',
        ] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->input($input));
            }
        }

        $limit = min(max($request->integer('limit', 100), 1), 500);

        return response()->json($query->limit($limit)->get());
    }
}
