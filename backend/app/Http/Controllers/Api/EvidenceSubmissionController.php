<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEvidenceSubmissionRequest;
use App\Http\Resources\EvidenceSubmissionResource;
use App\Models\EvidenceSubmission;
use App\Models\EvidenceTask;
use App\Models\FileAsset;
use App\Models\Teacher;
use App\Services\EvidenceService;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class EvidenceSubmissionController extends Controller
{
    public function store(StoreEvidenceSubmissionRequest $request, EvidenceTask $evidenceTask, EvidenceService $service)
    {
        abort_unless(AccessScope::taskIsVisible($evidenceTask, $request->user()), 403, 'No puedes subir archivos a una tarea que no te corresponde.');

        $files = $request->file('files', []);
        $assets = FileAsset::query()
            ->whereIn('id', $request->input('file_asset_ids', []))
            ->where('uploaded_by', $request->user()->id)
            ->get()
            ->keyBy('id');
        $assetIds = collect($request->input('file_asset_ids', []))
            ->map(fn ($id) => (int) $id)
            ->values();
        abort_if($assetIds->isNotEmpty() && $assets->count() !== $assetIds->count(), 403, 'No puedes usar archivos subidos por otro usuario.');

        $firstFile = array_shift($files);
        $firstAssetId = $assetIds->shift();
        $firstAsset = $firstAssetId ? $assets->get($firstAssetId) : null;

        $data = [
            'program_id' => $evidenceTask->program_id,
            'accreditation_cycle_id' => $evidenceTask->accreditation_cycle_id,
            'criterion_id' => $evidenceTask->accreditation_criterion_id,
            'subcriterion_id' => $evidenceTask->accreditation_subcriterion_id,
            'evidence_requirement_id' => $evidenceTask->evidence_requirement_id,
            'evidence_task_id' => $evidenceTask->id,
            'title' => $request->input('title'),
            'description' => $request->input('description'),
        ];

        if ($evidenceTask->context_type === 'teacher') {
            $data['teacher_id'] = $evidenceTask->context_id;
        }

        if (in_array($evidenceTask->context_type, ['course_offering', 'assessment_course'], true)) {
            $offering = $evidenceTask->courseOfferingContext()->with('assignments.teacher')->first();
            $teacher = Teacher::where('user_id', $request->user()?->id)->first();
            $data['course_id'] = $offering?->course_id;
            $data['teacher_id'] = $teacher?->id;
        }

        $evidence = $firstAsset
            ? $service->createFromAsset($data, $firstAsset, $request)
            : $service->create($data, $firstFile, $request);

        foreach ($files as $file) {
            $evidence = $service->addVersion($evidence, ['change_summary' => 'Archivo adicional cargado.'], $file, $request);
        }

        foreach ($assetIds as $assetId) {
            $evidence = $service->addVersionFromAsset($evidence, ['change_summary' => 'Archivo adicional cargado.'], $assets->get($assetId), $request);
        }

        return (new EvidenceSubmissionResource($evidence))
            ->additional(['message' => 'Evidencia registrada correctamente.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(EvidenceSubmission $evidenceSubmission, EvidenceService $service)
    {
        return new EvidenceSubmissionResource($evidenceSubmission->load($service->relations()));
    }

    public function review(Request $request, EvidenceSubmission $evidenceSubmission, EvidenceService $service)
    {
        $data = $request->validate([
            'decision' => ['required', 'string', 'in:observed,validated,approved,rejected'],
            'comments' => ['nullable', 'string'],
        ]);

        $action = match ($data['decision']) {
            'validated' => 'validate',
            'approved' => 'approve',
            'rejected' => 'reject',
            default => 'observe',
        };

        return (new EvidenceSubmissionResource($service->transition($evidenceSubmission, $action, $data['comments'] ?? null, $request)))
            ->additional(['message' => 'Revision registrada correctamente.']);
    }
}
