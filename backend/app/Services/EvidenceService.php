<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EvidenceReview;
use App\Models\EvidenceStatusHistory;
use App\Models\EvidenceSubmission;
use App\Models\EvidenceTask;
use App\Models\EvidenceVersion;
use App\Models\FileAsset;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EvidenceService
{
    public function create(array $data, UploadedFile $file, Request $request): EvidenceSubmission
    {
        $task = $this->resolveTask($data);
        $data = $this->hydrateContextFromTask($data, $task, $request);
        $asset = $this->storeFile($file, $data, $request);

        return $this->createFromAsset($data, $asset, $request);
    }

    public function createFromAsset(array $data, FileAsset $asset, Request $request): EvidenceSubmission
    {
        return DB::transaction(function () use ($data, $asset, $request) {
            $task = $this->resolveTask($data);
            $data = $this->hydrateContextFromTask($data, $task, $request);

            $evidence = EvidenceSubmission::create([
                'program_id' => $data['program_id'],
                'accreditation_cycle_id' => $data['accreditation_cycle_id'],
                'accreditation_criterion_id' => $data['criterion_id'],
                'accreditation_subcriterion_id' => $data['subcriterion_id'] ?? null,
                'evidence_requirement_id' => $data['evidence_requirement_id'],
                'evidence_task_id' => $task?->id,
                'course_id' => $data['course_id'] ?? null,
                'teacher_id' => $data['teacher_id'] ?? null,
                'current_file_asset_id' => $asset->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => 'uploaded',
                'version_number' => 1,
                'submitted_by' => $request->user()?->id,
                'submitted_at' => now(),
            ]);

            EvidenceVersion::create([
                'evidence_submission_id' => $evidence->id,
                'file_asset_id' => $asset->id,
                'version_number' => 1,
                'change_summary' => 'Version inicial.',
                'uploaded_by' => $request->user()?->id,
            ]);

            $this->recordReview($evidence, $request, 'submit', null, 'uploaded', 'Evidencia registrada.');

            if ($task) {
                $this->updateTaskStatus($task, 'uploaded', $evidence, $request, 'Evidencia registrada.');
            }

            $this->audit($request, 'evidence.created', $evidence, null, $evidence->toArray());

            return $evidence->load($this->relations());
        });
    }

    public function addVersion(EvidenceSubmission $evidence, array $data, UploadedFile $file, Request $request): EvidenceSubmission
    {
        $asset = $this->storeFile($file, [
            'program_id' => $evidence->program_id,
            'accreditation_cycle_id' => $evidence->accreditation_cycle_id,
            'criterion_id' => $evidence->accreditation_criterion_id,
            'evidence_task_id' => $evidence->evidence_task_id,
            'course_id' => $evidence->course_id,
            'teacher_id' => $evidence->teacher_id,
            'evidence_id' => $evidence->id,
        ], $request);

        return $this->addVersionFromAsset($evidence, $data, $asset, $request);
    }

    public function addVersionFromAsset(EvidenceSubmission $evidence, array $data, FileAsset $asset, Request $request): EvidenceSubmission
    {
        return DB::transaction(function () use ($evidence, $data, $asset, $request) {
            $from = $evidence->status;
            $to = $from === 'observed' ? 'corrected' : 'uploaded';
            $nextVersion = $evidence->version_number + 1;
            $old = $evidence->toArray();

            EvidenceVersion::create([
                'evidence_submission_id' => $evidence->id,
                'file_asset_id' => $asset->id,
                'version_number' => $nextVersion,
                'change_summary' => $data['change_summary'] ?? null,
                'uploaded_by' => $request->user()?->id,
            ]);

            $evidence->update([
                'current_file_asset_id' => $asset->id,
                'version_number' => $nextVersion,
                'status' => $to,
                'submitted_by' => $request->user()?->id,
                'submitted_at' => now(),
            ]);

            $this->recordReview($evidence, $request, 'correct', $from, $to, $data['change_summary'] ?? 'Nueva version subida.');

            if ($evidence->task) {
                $this->updateTaskStatus($evidence->task, $to, $evidence, $request, $data['change_summary'] ?? null);
            }

            $this->audit($request, 'evidence.version.created', $evidence, $old, $evidence->fresh()->toArray());

            return $evidence->fresh($this->relations());
        });
    }

    public function transition(EvidenceSubmission $evidence, string $action, ?string $comment, Request $request): EvidenceSubmission
    {
        $toStatus = match ($action) {
            'observe' => 'observed',
            'validate' => 'validated',
            'approve' => 'approved',
            'reject' => 'observed',
            default => $evidence->status,
        };

        return DB::transaction(function () use ($evidence, $action, $comment, $request, $toStatus) {
            $from = $evidence->status;
            $old = $evidence->toArray();
            $updates = ['status' => $toStatus];

            if ($action === 'observe' || $action === 'reject') {
                $updates['reviewed_by'] = $request->user()?->id;
                $updates['reviewed_at'] = now();
            }

            if ($action === 'validate') {
                $updates['validated_by'] = $request->user()?->id;
                $updates['validated_at'] = now();
            }

            if ($action === 'approve') {
                $updates['approved_by'] = $request->user()?->id;
                $updates['approved_at'] = now();
            }

            $evidence->update($updates);
            $this->recordReview($evidence, $request, $action, $from, $toStatus, $comment);

            if ($evidence->task) {
                $this->updateTaskStatus($evidence->task, $toStatus, $evidence, $request, $comment);
            }

            $this->audit($request, 'evidence.'.$action, $evidence, $old, $evidence->fresh()->toArray());

            return $evidence->fresh($this->relations());
        });
    }

    public function delete(EvidenceSubmission $evidence, Request $request): void
    {
        DB::transaction(function () use ($evidence, $request) {
            $old = $evidence->toArray();
            $evidence->delete();
            $this->audit($request, 'evidence.deleted', $evidence, $old, null);
        });
    }

    public function relations(): array
    {
        return [
            'program.faculty',
            'cycle.model',
            'cycle.term',
            'criterion',
            'subcriterion',
            'requirement',
            'task',
            'course',
            'teacher.user',
            'currentFile',
            'versions.file',
            'versions.uploader',
            'reviews.reviewer',
            'submittedBy',
        ];
    }

    private function storeFile(UploadedFile $file, array $context, ?Request $request = null): FileAsset
    {
        $disk = config('accreditation.storage_disk', 'public');
        $extension = strtolower($file->getClientOriginalExtension());
        $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'evidencia';
        $storedName = $baseName.'-'.Str::random(12).'.'.$extension;
        $folder = $this->storageFolder($context, $request);

        $path = $file->storeAs($folder, $storedName, $disk);

        return FileAsset::create([
            'uploaded_by' => auth()->id(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'visibility' => $disk === 'public' ? 'public' : 'private',
            'metadata' => [
                'client_mime_type' => $file->getClientMimeType(),
                'storage_context' => $this->storageMetadata($context, $request),
            ],
        ]);
    }

    private function storageFolder(array $context, ?Request $request = null): string
    {
        $segments = [
            'accreditation',
            'cycle-'.$context['accreditation_cycle_id'],
            'program-'.$context['program_id'],
            'criterion-'.$context['criterion_id'],
        ];

        if (! empty($context['evidence_task_id'])) {
            $segments[] = 'task-'.$context['evidence_task_id'];
        }

        if (! empty($context['course_id'])) {
            $segments[] = 'course-'.$context['course_id'];
        }

        if (! empty($context['teacher_id'])) {
            $segments[] = 'teacher-'.$context['teacher_id'];
        }

        if (! empty($context['evidence_id'])) {
            $segments[] = 'evidence-'.$context['evidence_id'];
        }

        return implode('/', $segments);
    }

    private function storageMetadata(array $context, ?Request $request = null): array
    {
        return [
            'accreditation_cycle_id' => $context['accreditation_cycle_id'] ?? null,
            'program_id' => $context['program_id'] ?? null,
            'criterion_id' => $context['criterion_id'] ?? null,
            'evidence_task_id' => $context['evidence_task_id'] ?? null,
            'course_id' => $context['course_id'] ?? null,
            'teacher_id' => $context['teacher_id'] ?? null,
            'evidence_id' => $context['evidence_id'] ?? null,
            'uploaded_by' => $request?->user()?->id ?? auth()->id(),
        ];
    }

    private function resolveTask(array $data): ?EvidenceTask
    {
        if (! empty($data['evidence_task_id'])) {
            return EvidenceTask::find($data['evidence_task_id']);
        }

        if (! empty($data['teacher_id']) && empty($data['course_id'])) {
            return EvidenceTask::query()
                ->where('program_id', $data['program_id'])
                ->where('accreditation_cycle_id', $data['accreditation_cycle_id'])
                ->where('accreditation_criterion_id', $data['criterion_id'])
                ->where('evidence_requirement_id', $data['evidence_requirement_id'])
                ->where('context_type', 'teacher')
                ->where('context_id', $data['teacher_id'])
                ->first();
        }

        return EvidenceTask::query()
            ->where('program_id', $data['program_id'])
            ->where('accreditation_cycle_id', $data['accreditation_cycle_id'])
            ->where('accreditation_criterion_id', $data['criterion_id'])
            ->where('evidence_requirement_id', $data['evidence_requirement_id'])
            ->when($data['subcriterion_id'] ?? null, fn ($query, $id) => $query->where('accreditation_subcriterion_id', $id))
            ->first();
    }

    private function hydrateContextFromTask(array $data, ?EvidenceTask $task, Request $request): array
    {
        if (! $task) {
            return $data;
        }

        if ($task->context_type === 'teacher') {
            $data['teacher_id'] = $data['teacher_id'] ?? $task->context_id;
        }

        if (in_array($task->context_type, ['course_offering', 'assessment_course'], true)) {
            $offering = $task->courseOfferingContext()->first();
            $teacher = Teacher::where('user_id', $request->user()?->id)->first();
            $data['course_id'] = $data['course_id'] ?? $offering?->course_id;
            $data['teacher_id'] = $data['teacher_id'] ?? $teacher?->id;
        }

        return $data;
    }

    private function updateTaskStatus(EvidenceTask $task, string $status, EvidenceSubmission $evidence, Request $request, ?string $comment): void
    {
        $from = $task->status;
        $task->update(['status' => $status]);

        EvidenceStatusHistory::create([
            'evidence_task_id' => $task->id,
            'evidence_submission_id' => $evidence->id,
            'changed_by' => $request->user()?->id,
            'from_status' => $from,
            'to_status' => $status,
            'comment' => $comment,
        ]);
    }

    private function recordReview(EvidenceSubmission $evidence, Request $request, string $action, ?string $from, string $to, ?string $comment): EvidenceReview
    {
        return EvidenceReview::create([
            'evidence_submission_id' => $evidence->id,
            'reviewer_id' => $request->user()?->id,
            'action' => $action,
            'comment' => $comment,
            'from_status' => $from,
            'to_status' => $to,
        ]);
    }

    private function audit(Request $request, string $action, EvidenceSubmission $evidence, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => EvidenceSubmission::class,
            'auditable_id' => $evidence->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
