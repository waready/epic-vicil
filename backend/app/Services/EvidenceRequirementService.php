<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EvidenceRequirementService
{
    public function consolidate(
        EvidenceRequirement $source,
        EvidenceRequirement $target,
        array $data,
        Request $request
    ): array {
        $this->validateConsolidation($source, $target, (bool) ($data['renumber_codes'] ?? true));

        return DB::transaction(function () use ($source, $target, $data, $request) {
            $source = EvidenceRequirement::query()->lockForUpdate()->findOrFail($source->id);
            $target = EvidenceRequirement::query()->lockForUpdate()->findOrFail($target->id);
            $oldSource = $source->toArray();
            $oldTarget = $target->toArray();
            $summary = [
                'submissions_moved' => 0,
                'tasks_moved' => 0,
                'tasks_consolidated' => 0,
                'codes_renumbered' => 0,
            ];

            $target->update(['name' => trim($data['target_name'])]);

            $summary['submissions_moved'] = DB::table('evidence_submissions')
                ->where('evidence_requirement_id', $source->id)
                ->update([
                    'evidence_requirement_id' => $target->id,
                    'accreditation_criterion_id' => $target->accreditation_criterion_id,
                    'accreditation_subcriterion_id' => $target->accreditation_subcriterion_id,
                    'updated_at' => now(),
                ]);

            $sourceTasks = EvidenceTask::query()
                ->where('evidence_requirement_id', $source->id)
                ->lockForUpdate()
                ->get();

            foreach ($sourceTasks as $sourceTask) {
                $matchingTask = EvidenceTask::query()
                    ->where('evidence_requirement_id', $target->id)
                    ->where('accreditation_cycle_id', $sourceTask->accreditation_cycle_id)
                    ->where('program_id', $sourceTask->program_id)
                    ->where('context_type', $sourceTask->context_type)
                    ->where('context_id', $sourceTask->context_id)
                    ->where('academic_term_id', $sourceTask->academic_term_id)
                    ->lockForUpdate()
                    ->first();

                if ($matchingTask) {
                    DB::table('evidence_submissions')
                        ->where('evidence_task_id', $sourceTask->id)
                        ->update([
                            'evidence_task_id' => $matchingTask->id,
                            'updated_at' => now(),
                        ]);

                    DB::table('evidence_status_histories')
                        ->where('evidence_task_id', $sourceTask->id)
                        ->update(['evidence_task_id' => $matchingTask->id]);

                    if (in_array($matchingTask->status, ['pending', 'assigned'], true)
                        && ! in_array($sourceTask->status, ['pending', 'assigned'], true)) {
                        $matchingTask->update(['status' => $sourceTask->status]);
                    }

                    $sourceTask->delete();
                    $summary['tasks_consolidated']++;

                    continue;
                }

                $sourceTask->update([
                    'accreditation_criterion_id' => $target->accreditation_criterion_id,
                    'accreditation_subcriterion_id' => $target->accreditation_subcriterion_id,
                    'evidence_requirement_id' => $target->id,
                    'instructions' => $target->name.'.',
                ]);
                $summary['tasks_moved']++;
            }

            $source->update([
                'code' => $this->legacyCode($source),
                'is_active' => false,
            ]);

            if ($data['renumber_codes'] ?? true) {
                $summary['codes_renumbered'] = $this->renumberActiveGroup($target, $oldSource['code']);
            }

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'evidence_requirement.consolidated',
                'auditable_type' => EvidenceRequirement::class,
                'auditable_id' => $target->id,
                'old_values' => ['source' => $oldSource, 'target' => $oldTarget],
                'new_values' => [
                    'source' => $source->fresh()->toArray(),
                    'target' => $target->fresh()->toArray(),
                    'summary' => $summary,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return [
                'message' => 'Requerimientos consolidados correctamente.',
                'summary' => $summary,
                'target' => $target->fresh(['criterion.accreditationModel:id,code,name', 'subcriterion:id,code,name']),
            ];
        });
    }

    private function validateConsolidation(
        EvidenceRequirement $source,
        EvidenceRequirement $target,
        bool $renumberCodes
    ): void {
        $errors = [];

        if ($source->is($target)) {
            $errors['target_requirement_id'] = 'Selecciona un requerimiento diferente como destino.';
        }

        if (! $source->is_active || ! $target->is_active) {
            $errors['target_requirement_id'] = 'El requerimiento de origen y el de destino deben estar activos.';
        }

        if ($source->accreditation_criterion_id !== $target->accreditation_criterion_id) {
            $errors['target_requirement_id'] = 'Ambos requerimientos deben pertenecer al mismo criterio.';
        }

        if ($source->applies_to !== $target->applies_to) {
            $errors['target_requirement_id'] = 'Ambos requerimientos deben aplicar al mismo tipo de contexto.';
        }

        if ($renumberCodes && $this->codePrefix($source->code) !== $this->codePrefix($target->code)) {
            $errors['renumber_codes'] = 'Solo se pueden renumerar codigos que compartan el mismo prefijo.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function renumberActiveGroup(EvidenceRequirement $target, ?string $sourceCode): int
    {
        $prefix = $this->codePrefix($sourceCode);

        if ($prefix === null) {
            return 0;
        }

        $requirements = EvidenceRequirement::query()
            ->where('accreditation_criterion_id', $target->accreditation_criterion_id)
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->filter(fn (EvidenceRequirement $requirement) => $this->codePrefix($requirement->code) === $prefix)
            ->values();

        $digits = max(2, strlen((string) $requirements->count()));
        $changed = 0;

        foreach ($requirements as $index => $requirement) {
            $newCode = $prefix.str_pad((string) ($index + 1), $digits, '0', STR_PAD_LEFT);

            if ($requirement->code === $newCode) {
                continue;
            }

            $requirement->update(['code' => $newCode]);
            $changed++;
        }

        return $changed;
    }

    private function codePrefix(?string $code): ?string
    {
        if (! $code || ! preg_match('/^(.*?)(\d+)$/', $code, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function legacyCode(EvidenceRequirement $requirement): string
    {
        $base = $requirement->code ?: 'REQ-'.$requirement->id;

        return Str::limit($base.'-INACTIVO-'.$requirement->id, 80, '');
    }
}
