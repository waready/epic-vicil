<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $path = database_path('data/icacit_folder_requirements_2026_06_30.csv');

        if (! file_exists($path)) {
            return;
        }

        $modelId = DB::table('accreditation_models')->where('code', 'ICACIT')->value('id');

        if (! $modelId) {
            return;
        }

        $rows = $this->csvRows($path);
        $now = now();
        $criterionIds = [];
        $importedRequirementIds = [];
        $importedRequirementCodes = [];
        $allowedExtensions = json_encode(config('accreditation.allowed_extensions'));

        foreach ($rows as $row) {
            $criterionCode = trim($row['criterion_code']);
            $criterionName = trim($row['criterion_name']);
            $criterionOrder = (int) preg_replace('/\D+/', '', $criterionCode);

            $criterion = DB::table('accreditation_criteria')
                ->where('accreditation_model_id', $modelId)
                ->where('code', $criterionCode)
                ->first();

            $criterionPayload = [
                'name' => $criterionName,
                'order' => $criterionOrder,
                'is_active' => true,
                'updated_at' => $now,
            ];

            if ($criterion) {
                DB::table('accreditation_criteria')->where('id', $criterion->id)->update($criterionPayload);
                $criterionId = $criterion->id;
            } else {
                $criterionId = DB::table('accreditation_criteria')->insertGetId(array_merge($criterionPayload, [
                    'accreditation_model_id' => $modelId,
                    'code' => $criterionCode,
                    'created_at' => $now,
                ]));
            }

            $criterionIds[$criterionCode] = $criterionId;

            $groupCode = trim($row['group_code']);
            $subcriterionCode = $criterionCode.'.'.$groupCode;
            $subcriterion = DB::table('accreditation_subcriteria')
                ->where('accreditation_criterion_id', $criterionId)
                ->where('code', $subcriterionCode)
                ->first();

            $subcriterionPayload = [
                'name' => trim($row['group_name']),
                'order' => $groupCode === 'DOC' ? 1 : 2,
                'is_active' => true,
                'updated_at' => $now,
            ];

            if ($subcriterion) {
                DB::table('accreditation_subcriteria')->where('id', $subcriterion->id)->update($subcriterionPayload);
                $subcriterionId = $subcriterion->id;
            } else {
                $subcriterionId = DB::table('accreditation_subcriteria')->insertGetId(array_merge($subcriterionPayload, [
                    'accreditation_criterion_id' => $criterionId,
                    'code' => $subcriterionCode,
                    'created_at' => $now,
                ]));
            }

            $requirementCode = trim($row['requirement_code']);
            $requirement = DB::table('evidence_requirements')
                ->where('accreditation_criterion_id', $criterionId)
                ->where('code', $requirementCode)
                ->first();

            $requirementPayload = [
                'accreditation_subcriterion_id' => $subcriterionId,
                'name' => trim($row['requirement_name']),
                'description' => $this->description($row),
                'applies_to' => 'program',
                'evidence_kind' => $groupCode === 'DOC' ? 'normative' : 'record',
                'is_required' => true,
                'allows_multiple_files' => true,
                'allowed_extensions' => $allowedExtensions,
                'order' => $this->requirementOrder($requirementCode),
                'is_active' => true,
                'updated_at' => $now,
            ];

            if ($requirement) {
                DB::table('evidence_requirements')->where('id', $requirement->id)->update($requirementPayload);
                $requirementId = $requirement->id;
            } else {
                $requirementId = DB::table('evidence_requirements')->insertGetId(array_merge($requirementPayload, [
                    'accreditation_criterion_id' => $criterionId,
                    'code' => $requirementCode,
                    'created_at' => $now,
                ]));
            }

            $importedRequirementIds[] = $requirementId;
            $importedRequirementCodes[] = $requirementCode;
        }

        $this->deactivateUnusedProgramRequirements($criterionIds, $importedRequirementCodes, $now);
        $this->syncProgramTasks($modelId, $importedRequirementIds, $now);
    }

    public function down(): void
    {
        //
    }

    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        $headers = fgetcsv($handle);
        $headers = array_map(
            fn ($header) => trim(ltrim((string) $header, "\xEF\xBB\xBF"), "\"' \t\n\r\0\x0B"),
            $headers
        );
        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rows[] = array_combine($headers, $values);
        }

        fclose($handle);

        return $rows;
    }

    private function description(array $row): ?string
    {
        $parts = ['Fuente: HR 30-06-2026, fila '.$row['source_row'].'.'];

        if (! empty($row['note'])) {
            $parts[] = 'Nota: '.$row['note'];
        }

        return implode(' ', $parts);
    }

    private function requirementOrder(string $code): int
    {
        if (preg_match('/-(DOC|REG)-(\d+)$/', $code, $matches)) {
            return ($matches[1] === 'DOC' ? 0 : 500) + (int) $matches[2];
        }

        return 999;
    }

    private function deactivateUnusedProgramRequirements(array $criterionIds, array $importedCodes, $now): void
    {
        $oldRequirements = DB::table('evidence_requirements')
            ->whereIn('accreditation_criterion_id', array_values($criterionIds))
            ->where('applies_to', 'program')
            ->whereNotIn('code', $importedCodes)
            ->get(['id']);

        foreach ($oldRequirements as $requirement) {
            $hasSubmissions = DB::table('evidence_submissions')
                ->where('evidence_requirement_id', $requirement->id)
                ->whereNull('deleted_at')
                ->exists();

            if ($hasSubmissions) {
                continue;
            }

            DB::table('evidence_requirements')
                ->where('id', $requirement->id)
                ->update(['is_active' => false, 'updated_at' => $now]);

            DB::table('evidence_tasks')
                ->where('evidence_requirement_id', $requirement->id)
                ->whereNull('deleted_at')
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('evidence_submissions')
                        ->whereColumn('evidence_submissions.evidence_task_id', 'evidence_tasks.id')
                        ->whereNull('evidence_submissions.deleted_at');
                })
                ->update(['deleted_at' => $now, 'updated_at' => $now]);
        }
    }

    private function syncProgramTasks(int $modelId, array $requirementIds, $now): void
    {
        $cycles = DB::table('accreditation_cycles')
            ->where('accreditation_model_id', $modelId)
            ->whereIn('status', ['active', 'planning'])
            ->get();

        $requirements = DB::table('evidence_requirements')
            ->whereIn('id', $requirementIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $assignedTo = $this->defaultAssignee();

        foreach ($cycles as $cycle) {
            foreach ($requirements as $requirement) {
                $attributes = [
                    'accreditation_cycle_id' => $cycle->id,
                    'evidence_requirement_id' => $requirement->id,
                    'context_type' => 'program',
                    'context_id' => $cycle->program_id,
                ];

                $task = DB::table('evidence_tasks')
                    ->where($attributes)
                    ->first();

                $payload = [
                    'program_id' => $cycle->program_id,
                    'accreditation_criterion_id' => $requirement->accreditation_criterion_id,
                    'accreditation_subcriterion_id' => $requirement->accreditation_subcriterion_id,
                    'assigned_to' => $assignedTo,
                    'priority' => 'high',
                    'instructions' => 'Carpeta institucional: '.$requirement->name.'.',
                    'deleted_at' => null,
                    'updated_at' => $now,
                ];

                if ($task) {
                    DB::table('evidence_tasks')->where('id', $task->id)->update($payload);

                    continue;
                }

                DB::table('evidence_tasks')->insert(array_merge($attributes, $payload, [
                    'status' => 'pending',
                    'created_at' => $now,
                ]));
            }
        }
    }

    private function defaultAssignee(): ?int
    {
        $roleNames = ['coordinador_acreditacion', 'admin_facultad', 'super_admin'];

        foreach ($roleNames as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');

            if (! $roleId) {
                continue;
            }

            $userId = DB::table('model_has_roles')
                ->join('users', 'users.id', '=', 'model_has_roles.model_id')
                ->where('model_has_roles.role_id', $roleId)
                ->where('users.is_active', true)
                ->whereNull('users.deleted_at')
                ->orderBy('users.id')
                ->value('users.id');

            if ($userId) {
                return (int) $userId;
            }
        }

        return DB::table('users')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->value('id');
    }
};
