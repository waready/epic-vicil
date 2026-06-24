<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewEvidenceRequest;
use App\Http\Requests\StoreEvidenceRequest;
use App\Http\Requests\StoreEvidenceVersionRequest;
use App\Http\Resources\EvidenceSubmissionResource;
use App\Models\EvidenceSubmission;
use App\Models\EvidenceTask;
use App\Models\FileAsset;
use App\Services\EvidenceService;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class EvidenceController extends Controller
{
    public function index(Request $request, EvidenceService $service)
    {
        $sortBy = $request->input('sort_by', 'updated_at');
        $sortBy = in_array($sortBy, ['title', 'status', 'submitted_at', 'updated_at', 'version_number'], true) ? $sortBy : 'updated_at';
        $direction = $request->boolean('descending', true) ? 'desc' : 'asc';

        $query = EvidenceSubmission::query()
            ->select([
                'id',
                'program_id',
                'accreditation_cycle_id',
                'accreditation_criterion_id',
                'accreditation_subcriterion_id',
                'evidence_requirement_id',
                'evidence_task_id',
                'course_id',
                'teacher_id',
                'current_file_asset_id',
                'title',
                'description',
                'status',
                'version_number',
                'submitted_by',
                'submitted_at',
                'reviewed_at',
                'validated_at',
                'approved_at',
                'created_at',
                'updated_at',
            ])
            ->with([
                'program:id,code,name,faculty_id',
                'cycle:id,accreditation_model_id,program_id,academic_term_id,name,year,status',
                'cycle.model:id,code,name',
                'cycle.term:id,academic_year_id,code,name',
                'criterion:id,code,name,order',
                'requirement:id,accreditation_criterion_id,accreditation_subcriterion_id,code,name,applies_to,evidence_kind',
                'teacher:id,user_id,first_name,last_name,email',
                'teacher.user:id,name,email',
                'currentFile:id,disk,path,original_name,extension,mime_type,size_bytes',
                'submittedBy:id,name,email',
            ])
            ->orderBy($sortBy, $direction);

        AccessScope::applyEvidenceVisibility($query, $request->user());

        foreach ([
            'program_id' => 'program_id',
            'cycle_id' => 'accreditation_cycle_id',
            'accreditation_cycle_id' => 'accreditation_cycle_id',
            'criterion_id' => 'accreditation_criterion_id',
            'evidence_requirement_id' => 'evidence_requirement_id',
            'teacher_id' => 'teacher_id',
            'status' => 'status',
        ] as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->input($input));
            }
        }

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('requirement', fn ($requirement) => $requirement->where('name', 'like', "%{$search}%"));
            });
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return EvidenceSubmissionResource::collection($query->paginate($perPage));
    }

    public function store(StoreEvidenceRequest $request, EvidenceService $service)
    {
        $data = $request->validated();

        if (AccessScope::isTeacherOnly($request->user())) {
            $task = ! empty($data['evidence_task_id']) ? EvidenceTask::find($data['evidence_task_id']) : null;
            abort_unless($task && AccessScope::taskIsVisible($task, $request->user()), 403, 'No puedes registrar evidencias fuera de tus tareas asignadas.');
        }

        $asset = $this->assetForRequest($request, $data['file_asset_id'] ?? null);
        $evidence = $asset
            ? $service->createFromAsset($data, $asset, $request)
            : $service->create($data, $request->file('file'), $request);

        return (new EvidenceSubmissionResource($evidence))
            ->additional(['message' => 'Evidencia registrada correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, EvidenceSubmission $evidence, EvidenceService $service)
    {
        abort_unless(AccessScope::evidenceIsVisible($evidence, $request->user()), 403, 'No puedes ver esta evidencia.');

        return new EvidenceSubmissionResource($evidence->load($service->relations()));
    }

    public function version(StoreEvidenceVersionRequest $request, EvidenceSubmission $evidence, EvidenceService $service)
    {
        abort_unless(AccessScope::evidenceIsVisible($evidence, $request->user()), 403, 'No puedes modificar esta evidencia.');

        $data = $request->validated();
        $asset = $this->assetForRequest($request, $data['file_asset_id'] ?? null);
        $updated = $asset
            ? $service->addVersionFromAsset($evidence, $data, $asset, $request)
            : $service->addVersion($evidence, $data, $request->file('file'), $request);

        return (new EvidenceSubmissionResource($updated))
            ->additional(['message' => 'Nueva version registrada correctamente.']);
    }

    public function observe(ReviewEvidenceRequest $request, EvidenceSubmission $evidence, EvidenceService $service)
    {
        return (new EvidenceSubmissionResource($service->transition($evidence, 'observe', $request->input('comment'), $request)))
            ->additional(['message' => 'Observacion registrada correctamente.']);
    }

    public function validateEvidence(ReviewEvidenceRequest $request, EvidenceSubmission $evidence, EvidenceService $service)
    {
        return (new EvidenceSubmissionResource($service->transition($evidence, 'validate', $request->input('comment'), $request)))
            ->additional(['message' => 'Evidencia validada correctamente.']);
    }

    public function approve(ReviewEvidenceRequest $request, EvidenceSubmission $evidence, EvidenceService $service)
    {
        return (new EvidenceSubmissionResource($service->transition($evidence, 'approve', $request->input('comment'), $request)))
            ->additional(['message' => 'Evidencia aprobada correctamente.']);
    }

    public function destroy(Request $request, EvidenceSubmission $evidence, EvidenceService $service)
    {
        $service->delete($evidence, $request);

        return response()->json(['message' => 'Evidencia eliminada correctamente.']);
    }

    private function assetForRequest(Request $request, mixed $fileAssetId): ?FileAsset
    {
        if (! $fileAssetId) {
            return null;
        }

        return FileAsset::query()
            ->whereKey($fileAssetId)
            ->where('uploaded_by', $request->user()->id)
            ->firstOrFail();
    }
}
