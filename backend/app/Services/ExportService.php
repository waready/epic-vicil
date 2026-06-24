<?php

namespace App\Services;

use App\Models\AccreditationCycle;
use App\Models\AuditLog;
use App\Models\EvidenceSubmission;
use App\Models\ExportJob;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ExportService
{
    public function evidencesZip(array $filters, Request $request): ExportJob
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extension PHP zip es requerida para generar exportaciones.');
        }

        $cycle = AccreditationCycle::with('model')->findOrFail($filters['accreditation_cycle_id']);
        $program = Program::findOrFail($filters['program_id']);
        $statuses = $filters['statuses'] ?? ['validated', 'approved', 'ready_to_export'];
        $disk = config('accreditation.export_disk', 'local');

        $job = ExportJob::create([
            'requested_by' => $request->user()?->id,
            'accreditation_cycle_id' => $cycle->id,
            'program_id' => $program->id,
            'export_type' => 'zip',
            'status' => 'running',
            'disk' => $disk,
            'filters' => $filters,
            'started_at' => now(),
        ]);

        try {
            $baseName = $this->folderName('ACREDITACION_'.$cycle->model->code.'_'.$cycle->year.'_'.$program->code);
            $relativePath = 'exports/'.$baseName.'_'.$job->id.'.zip';
            $absolutePath = Storage::disk($disk)->path($relativePath);

            if (! is_dir(dirname($absolutePath))) {
                mkdir(dirname($absolutePath), 0775, true);
            }

            $zip = new ZipArchive();
            if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo crear el archivo ZIP.');
            }

            $temporaryFiles = [];
            $evidences = EvidenceSubmission::query()
                ->with(['cycle.model', 'program', 'criterion', 'requirement', 'currentFile'])
                ->where('accreditation_cycle_id', $cycle->id)
                ->where('program_id', $program->id)
                ->whereIn('status', $statuses)
                ->orderBy('accreditation_criterion_id')
                ->orderBy('title')
                ->get();

            foreach ($evidences as $evidence) {
                if (! $evidence->currentFile) {
                    continue;
                }

                $asset = $evidence->currentFile;
                $sourceDisk = Storage::disk($asset->disk);
                if (! $sourceDisk->exists($asset->path)) {
                    continue;
                }

                $criterionFolder = $this->folderName($evidence->criterion->code.'_'.$evidence->criterion->name);
                $requirementFolder = $this->folderName($evidence->requirement->code.'_'.$evidence->requirement->name);
                $versionPrefix = 'v'.$evidence->version_number.'_';
                $zipPath = $baseName.'/'.$criterionFolder.'/'.$requirementFolder.'/'.$versionPrefix.$this->fileName($asset->original_name);
                $sourcePath = $this->localPathForZip($sourceDisk, $asset->path, $temporaryFiles);

                $zip->addFile($sourcePath, $zipPath);
            }

            $zip->close();

            foreach ($temporaryFiles as $temporaryFile) {
                @unlink($temporaryFile);
            }

            $job->update([
                'status' => 'finished',
                'path' => $relativePath,
                'stats' => [
                    'total_files' => $evidences->filter(fn ($evidence) => $evidence->currentFile)->count(),
                    'statuses' => $statuses,
                ],
                'finished_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'export.evidences_zip',
                'auditable_type' => ExportJob::class,
                'auditable_id' => $job->id,
                'new_values' => $job->fresh()->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $exception) {
            $job->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }

        return $job->fresh();
    }

    private function folderName(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9]+/', '_')
            ->trim('_')
            ->limit(120, '')
            ->toString();
    }

    private function fileName(string $value): string
    {
        $name = Str::of(pathinfo($value, PATHINFO_FILENAME))->ascii()->replaceMatches('/[^A-Za-z0-9]+/', '_')->trim('_')->limit(100, '');
        $extension = pathinfo($value, PATHINFO_EXTENSION);

        return $name.($extension ? '.'.$extension : '');
    }

    private function localPathForZip($disk, string $path, array &$temporaryFiles): string
    {
        try {
            $localPath = $disk->path($path);
            if (is_string($localPath) && file_exists($localPath)) {
                return $localPath;
            }
        } catch (\Throwable) {
            //
        }

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $temporaryPath = tempnam($tmpDir, 'export_');
        file_put_contents($temporaryPath, $disk->get($path));
        $temporaryFiles[] = $temporaryPath;

        return $temporaryPath;
    }
}
