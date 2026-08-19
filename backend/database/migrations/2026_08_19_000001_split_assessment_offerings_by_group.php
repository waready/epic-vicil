<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const GROUP_TEACHERS = [
        'RE-I01' => [
            'A' => 'mrgarcia@docentes.com',
            'B' => 'fpmamani@docentes.com',
        ],
        'RE-I02' => [
            'A' => 'mdmendoza@docentes.com',
            'B' => 'aacondori@docentes.com',
        ],
        'RE-I03' => [
            'A' => 'whlipa@docentes.com',
            'B' => 'gzdelariva@docentes.com',
            'C' => 'hrvilca@docentes.com',
        ],
        'RE-I04' => [
            'A' => 'daquintanilla@docentes.com',
            'B' => 'gzdelariva@docentes.com',
            'C' => 'gnnajar@docentes.com',
        ],
        'RE-I05' => [
            'A' => 'mdmendoza@docentes.com',
            'B' => 'adroque@docentes.com',
        ],
        'RE-I06' => [
            'A' => 'gnnajar@docentes.com',
            'B' => 'aacondori@docentes.com',
        ],
        'RE-I07' => [
            'A' => 'zmellado@docentes.com',
            'B' => 'nzevallos@docentes.com',
        ],
        'RE-I08' => [
            'A' => 'daquintanilla@docentes.com',
            'B' => 'nlsuca@docentes.com',
        ],
        'RE-I09' => [
            'A' => 'dequinto@docentes.com',
            'B' => 'jmedina@docentes.com',
            'C' => 'eamolina@docentes.com',
        ],
        'RE-I10' => [
            'A' => 'cagonzales@docentes.com',
        ],
        'RE-I11' => [
            'A' => 'cagonzales@docentes.com',
            'B' => 'gnnajar@docentes.com',
        ],
        'RE-I12' => [
            'A' => 'daquintanilla@docentes.com',
            'B' => 'gzdelariva@docentes.com',
            'C' => 'gnnajar@docentes.com',
        ],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::GROUP_TEACHERS as $resultCode => $groups) {
                $this->splitOffering($resultCode, $groups);
            }
        });
    }

    public function down(): void
    {
        // Group offerings may receive evidence immediately, so they are not merged automatically.
    }

    private function splitOffering(string $resultCode, array $groups): void
    {
        $legacySection = 'ASSESSMENT-'.$resultCode;
        $source = DB::table('course_offerings')
            ->where('is_assessment_course', true)
            ->where('assessment_result_code', $resultCode)
            ->whereNull('deleted_at')
            ->orderByRaw('CASE WHEN section = ? THEN 0 WHEN section = ? THEN 1 ELSE 2 END', [$legacySection, 'A'])
            ->orderBy('id')
            ->first();

        if (! $source) {
            return;
        }

        foreach ($groups as $group => $teacherEmail) {
            $teacher = $this->teacherByEmail($teacherEmail);
            $offeringId = $group === 'A'
                ? (int) $source->id
                : $this->findOrCreateOffering($source, $resultCode, $group);

            DB::table('course_offerings')
                ->where('id', $offeringId)
                ->update([
                    'program_id' => $source->program_id,
                    'academic_term_id' => $source->academic_term_id,
                    'course_id' => $source->course_id,
                    'section' => $group,
                    'group_code' => $resultCode.'-'.$group,
                    'enrolled_count' => $source->enrolled_count,
                    'status' => $source->status,
                    'is_assessment_course' => true,
                    'assessment_result_code' => $source->assessment_result_code,
                    'assessment_result_name' => $source->assessment_result_name,
                    'requires_assessment_video' => $source->requires_assessment_video,
                    'requires_assessment_systematization' => $source->requires_assessment_systematization,
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);

            $this->syncMainTeacher($offeringId, (int) $teacher->teacher_id);
            $this->syncEvidenceTasks($source, $offeringId, $group, $teacher);
        }
    }

    private function teacherByEmail(string $email): object
    {
        $teacher = DB::table('teachers')
            ->join('users', 'users.id', '=', 'teachers.user_id')
            ->where('users.email', $email)
            ->whereNull('users.deleted_at')
            ->whereNull('teachers.deleted_at')
            ->first([
                'teachers.id as teacher_id',
                'teachers.user_id',
                'users.name as teacher_name',
            ]);

        if (! $teacher) {
            throw new RuntimeException("The assessment teacher {$email} was not found.");
        }

        return $teacher;
    }

    private function findOrCreateOffering(object $source, string $resultCode, string $group): int
    {
        $existing = DB::table('course_offerings')
            ->where('program_id', $source->program_id)
            ->where('academic_term_id', $source->academic_term_id)
            ->where('course_id', $source->course_id)
            ->where('is_assessment_course', true)
            ->where('assessment_result_code', $resultCode)
            ->where('section', $group)
            ->orderBy('id')
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('course_offerings')->insertGetId([
            'program_id' => $source->program_id,
            'academic_term_id' => $source->academic_term_id,
            'course_id' => $source->course_id,
            'section' => $group,
            'group_code' => $resultCode.'-'.$group,
            'enrolled_count' => $source->enrolled_count,
            'status' => $source->status,
            'is_assessment_course' => true,
            'assessment_result_code' => $source->assessment_result_code,
            'assessment_result_name' => $source->assessment_result_name,
            'requires_assessment_video' => $source->requires_assessment_video,
            'requires_assessment_systematization' => $source->requires_assessment_systematization,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function syncMainTeacher(int $offeringId, int $teacherId): void
    {
        $weeklyHours = DB::table('course_assignments')
            ->where('course_offering_id', $offeringId)
            ->where('teacher_id', $teacherId)
            ->value('weekly_hours');

        DB::table('course_assignments')
            ->where('course_offering_id', $offeringId)
            ->delete();

        DB::table('course_assignments')->insert([
            'course_offering_id' => $offeringId,
            'teacher_id' => $teacherId,
            'role' => 'main',
            'weekly_hours' => $weeklyHours,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function syncEvidenceTasks(object $source, int $offeringId, string $group, object $teacher): void
    {
        DB::table('evidence_tasks')
            ->where('context_type', 'assessment_course')
            ->where('context_id', $offeringId)
            ->update([
                'assigned_to' => $teacher->user_id,
                'updated_at' => now(),
            ]);

        $cycles = DB::table('accreditation_cycles')
            ->where('program_id', $source->program_id)
            ->whereIn('status', ['planning', 'active'])
            ->where(function ($query) use ($source) {
                $query->whereNull('academic_term_id')
                    ->orWhere('academic_term_id', $source->academic_term_id);
            })
            ->get();

        foreach ($cycles as $cycle) {
            $requirements = DB::table('evidence_requirements')
                ->join('accreditation_criteria', 'accreditation_criteria.id', '=', 'evidence_requirements.accreditation_criterion_id')
                ->where('accreditation_criteria.accreditation_model_id', $cycle->accreditation_model_id)
                ->where('evidence_requirements.applies_to', 'assessment_course')
                ->where('evidence_requirements.is_active', true)
                ->orderBy('evidence_requirements.order')
                ->get([
                    'evidence_requirements.*',
                ]);

            foreach ($requirements as $requirement) {
                if ($requirement->code === 'C3-ASS-04' && ! $source->requires_assessment_video) {
                    continue;
                }

                if ($requirement->code === 'C3-ASS-05' && ! $source->requires_assessment_systematization) {
                    continue;
                }

                $attributes = [
                    'accreditation_cycle_id' => $cycle->id,
                    'evidence_requirement_id' => $requirement->id,
                    'context_type' => 'assessment_course',
                    'context_id' => $offeringId,
                ];
                $task = DB::table('evidence_tasks')
                    ->where($attributes)
                    ->orderBy('id')
                    ->first();
                $metadata = json_encode([
                    'assessment_result_code' => $source->assessment_result_code,
                    'assessment_result_name' => $source->assessment_result_name,
                    'assessment_group' => $group,
                    'requires_video' => (bool) $source->requires_assessment_video,
                    'requires_systematization' => (bool) $source->requires_assessment_systematization,
                ], JSON_UNESCAPED_UNICODE);
                $payload = [
                    'program_id' => $source->program_id,
                    'accreditation_criterion_id' => $requirement->accreditation_criterion_id,
                    'accreditation_subcriterion_id' => $requirement->accreditation_subcriterion_id,
                    'academic_term_id' => $source->academic_term_id,
                    'assigned_to' => $teacher->user_id,
                    'priority' => $requirement->is_required ? 'high' : 'normal',
                    'instructions' => 'Assessment '.trim($source->assessment_result_code.' '.$source->assessment_result_name)
                        .' - Grupo '.$group.': '.$requirement->name.'.',
                    'metadata' => $metadata,
                    'deleted_at' => null,
                    'updated_at' => now(),
                ];

                if ($task) {
                    DB::table('evidence_tasks')->where('id', $task->id)->update($payload);

                    continue;
                }

                DB::table('evidence_tasks')->insert(array_merge($attributes, $payload, [
                    'status' => 'pending',
                    'created_at' => now(),
                ]));
            }
        }
    }
};
