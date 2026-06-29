<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvidenceTask;
use App\Models\FileAsset;
use App\Models\Teacher;
use App\Support\AccessScope;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DirectUploadController extends Controller
{
    public function presign(Request $request)
    {
        abort_unless(config('accreditation.direct_upload_enabled'), 422, 'La subida directa no esta habilitada.');

        $disk = config('accreditation.direct_upload_disk', 's3');
        abort_unless($disk === 's3', 422, 'La subida directa requiere un disco S3 compatible.');
        abort_unless($this->s3IsConfigured(), 422, 'Configura las credenciales S3/Spaces antes de usar subida directa.');

        $maxBytes = (int) config('accreditation.direct_upload_max_mb', 2048) * 1024 * 1024;
        $data = $request->validate([
            'evidence_task_id' => ['nullable', 'exists:evidence_tasks,id'],
            'program_id' => ['required_without:evidence_task_id', 'exists:programs,id'],
            'accreditation_cycle_id' => ['required_without:evidence_task_id', 'exists:accreditation_cycles,id'],
            'criterion_id' => ['required_without:evidence_task_id', 'exists:accreditation_criteria,id'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'original_name' => ['required', 'string', 'max:512'],
            'mime_type' => ['nullable', 'string', 'max:150'],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:'.$maxBytes],
        ]);

        $context = $this->contextFromRequest($data, $request);
        $context['uploaded_by'] = $request->user()->id;
        $extension = strtolower(pathinfo($data['original_name'], PATHINFO_EXTENSION));
        abort_unless(in_array($extension, config('accreditation.allowed_extensions'), true), 422, 'Tipo de archivo no permitido.');

        $baseName = Str::slug(pathinfo($data['original_name'], PATHINFO_FILENAME)) ?: 'evidencia';
        $storedName = $baseName.'-'.Str::random(18).'.'.$extension;
        $path = $this->storageFolder($context, $request).'/'.$storedName;

        $expiresAt = now()->addMinutes((int) config('accreditation.direct_upload_expiration_minutes', 15));
        $client = $this->s3Client();
        $command = $client->getCommand('PutObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key' => $path,
            'ContentType' => $data['mime_type'] ?: 'application/octet-stream',
        ]);
        $requestUrl = $client->createPresignedRequest($command, $expiresAt);

        return response()->json([
            'method' => 'PUT',
            'upload_url' => (string) $requestUrl->getUri(),
            'headers' => [
                'Content-Type' => $data['mime_type'] ?: 'application/octet-stream',
            ],
            'expires_at' => $expiresAt->toISOString(),
            'file' => [
                'disk' => $disk,
                'path' => $path,
                'original_name' => $data['original_name'],
                'stored_name' => $storedName,
                'extension' => $extension,
                'mime_type' => $data['mime_type'] ?: 'application/octet-stream',
                'size_bytes' => (int) $data['size_bytes'],
                'metadata' => [
                    'storage_context' => $context,
                ],
            ],
        ]);
    }

    public function complete(Request $request)
    {
        $disk = config('accreditation.direct_upload_disk', 's3');
        abort_unless($disk === 's3' && $this->s3IsConfigured(), 422, 'La subida directa no esta configurada.');

        $data = $request->validate([
            'disk' => ['required', Rule::in([$disk])],
            'path' => ['required', 'string', 'max:1024'],
            'original_name' => ['required', 'string', 'max:512'],
            'stored_name' => ['nullable', 'string', 'max:512'],
            'extension' => ['required', 'string', 'max:30'],
            'mime_type' => ['nullable', 'string', 'max:150'],
            'size_bytes' => ['required', 'integer', 'min:1'],
            'checksum' => ['nullable', 'string', 'max:128'],
        ]);

        abort_unless(str_starts_with($data['path'], 'accreditation/direct/'), 403, 'La ruta de subida directa no es valida.');
        abort_unless((int) $request->input('metadata.storage_context.uploaded_by') === (int) $request->user()->id, 403, 'No puedes completar una subida que no te corresponde.');
        abort_unless(in_array(strtolower($data['extension']), config('accreditation.allowed_extensions'), true), 422, 'Tipo de archivo no permitido.');
        abort_unless(Storage::disk($disk)->exists($data['path']), 422, 'El archivo no existe en el almacenamiento externo.');

        $verifiedSize = null;
        try {
            $verifiedSize = Storage::disk($disk)->size($data['path']);
        } catch (\Throwable) {
            $verifiedSize = null;
        }

        if ($verifiedSize !== null && (int) $verifiedSize !== (int) $data['size_bytes']) {
            abort(422, 'El tamano del archivo no coincide con la subida.');
        }

        $asset = FileAsset::updateOrCreate(
            ['disk' => $disk, 'path' => $data['path']],
            [
                'uploaded_by' => $request->user()->id,
                'original_name' => $data['original_name'],
                'stored_name' => $data['stored_name'] ?? basename($data['path']),
                'mime_type' => $data['mime_type'] ?: 'application/octet-stream',
                'extension' => strtolower($data['extension']),
                'size_bytes' => $verifiedSize ?? (int) $data['size_bytes'],
                'checksum' => $data['checksum'] ?? null,
                'visibility' => 'private',
                'metadata' => [
                    'direct_upload' => true,
                    'verified_size_bytes' => $verifiedSize,
                    'client_declared_size_bytes' => (int) $data['size_bytes'],
                    'storage_context' => $request->input('metadata.storage_context'),
                ],
            ]
        );

        return response()->json([
            'message' => 'Archivo confirmado correctamente.',
            'data' => $asset,
        ], 201);
    }

    private function contextFromRequest(array $data, Request $request): array
    {
        if (! empty($data['evidence_task_id'])) {
            $task = EvidenceTask::findOrFail($data['evidence_task_id']);
            abort_unless(AccessScope::taskIsVisible($task, $request->user()), 403, 'No puedes subir archivos a una tarea que no te corresponde.');

            return [
                'program_id' => $task->program_id,
                'accreditation_cycle_id' => $task->accreditation_cycle_id,
                'criterion_id' => $task->accreditation_criterion_id,
                'evidence_task_id' => $task->id,
                'course_id' => $this->courseIdFromTask($task, $request),
                'teacher_id' => $this->teacherIdFromTask($task, $request),
            ];
        }

        if (! empty($data['course_id']) || ! empty($data['teacher_id'])) {
            abort_unless(
                $request->user()?->hasAnyPermission(['manage.catalogs', 'manage.accreditation']),
                403,
                'No puedes definir el contexto academico de esta subida.'
            );
        }

        return [
            'program_id' => $data['program_id'],
            'accreditation_cycle_id' => $data['accreditation_cycle_id'],
            'criterion_id' => $data['criterion_id'],
            'course_id' => $data['course_id'] ?? null,
            'teacher_id' => $data['teacher_id'] ?? null,
        ];
    }

    private function storageFolder(array $context, Request $request): string
    {
        $segments = [
            'accreditation',
            'direct',
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

        return implode('/', $segments);
    }

    private function teacherIdFromTask(EvidenceTask $task, Request $request): ?int
    {
        if ($task->context_type === 'teacher') {
            return (int) $task->context_id;
        }

        if (in_array($task->context_type, ['course_offering', 'assessment_course'], true)) {
            return Teacher::where('user_id', $task->assigned_to)->value('id')
                ?: Teacher::where('user_id', $request->user()?->id)->value('id');
        }

        return null;
    }

    private function courseIdFromTask(EvidenceTask $task, Request $request): ?int
    {
        if (in_array($task->context_type, ['course_offering', 'assessment_course'], true)) {
            return $task->courseOfferingContext()->value('course_id');
        }

        return null;
    }

    private function s3IsConfigured(): bool
    {
        return filled(config('filesystems.disks.s3.key'))
            && filled(config('filesystems.disks.s3.secret'))
            && filled(config('filesystems.disks.s3.bucket'))
            && filled(config('filesystems.disks.s3.endpoint'));
    }

    private function s3Client(): S3Client
    {
        $config = config('filesystems.disks.s3');

        return new S3Client([
            'version' => 'latest',
            'region' => $config['region'] ?: 'us-east-1',
            'endpoint' => $config['endpoint'],
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
            'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? false),
        ]);
    }
}
