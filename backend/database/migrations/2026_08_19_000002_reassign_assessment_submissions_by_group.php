<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $offerings = DB::table('course_offerings as co')
                ->join('course_assignments as ca', function ($join) {
                    $join->on('ca.course_offering_id', '=', 'co.id')
                        ->where('ca.role', 'main');
                })
                ->join('teachers as t', 't.id', '=', 'ca.teacher_id')
                ->where('co.is_assessment_course', true)
                ->whereNull('co.deleted_at')
                ->get([
                    'co.id',
                    'co.program_id',
                    'co.academic_term_id',
                    'co.course_id',
                    'co.assessment_result_code',
                    'co.assessment_result_name',
                    'co.section',
                    't.id as teacher_id',
                    't.user_id',
                ]);

            if ($offerings->isEmpty()) {
                return;
            }

            $offeringByTeacher = $offerings->keyBy(fn ($offering) => $this->offeringKey(
                $offering->program_id,
                $offering->academic_term_id,
                $offering->course_id,
                $offering->assessment_result_code,
                $offering->user_id,
            ));
            $submissions = DB::table('evidence_submissions as es')
                ->join('evidence_tasks as et', 'et.id', '=', 'es.evidence_task_id')
                ->join('course_offerings as co', 'co.id', '=', 'et.context_id')
                ->where('et.context_type', 'assessment_course')
                ->where('co.is_assessment_course', true)
                ->whereNull('co.deleted_at')
                ->get([
                    'es.id',
                    'es.evidence_task_id',
                    'es.submitted_by',
                    'et.accreditation_cycle_id',
                    'et.evidence_requirement_id',
                    'co.program_id',
                    'co.academic_term_id',
                    'co.course_id',
                    'co.assessment_result_code',
                ]);
            $affectedTaskIds = [];

            foreach ($submissions as $submission) {
                if (! $submission->submitted_by) {
                    continue;
                }

                $targetOffering = $offeringByTeacher->get($this->offeringKey(
                    $submission->program_id,
                    $submission->academic_term_id,
                    $submission->course_id,
                    $submission->assessment_result_code,
                    $submission->submitted_by,
                ));

                if (! $targetOffering) {
                    continue;
                }

                $targetTaskId = $this->targetTaskId($submission, $targetOffering);

                if ($targetTaskId === (int) $submission->evidence_task_id) {
                    continue;
                }

                DB::table('evidence_submissions')
                    ->where('id', $submission->id)
                    ->update([
                        'evidence_task_id' => $targetTaskId,
                        'teacher_id' => $targetOffering->teacher_id,
                    ]);
                DB::table('evidence_status_histories')
                    ->where('evidence_submission_id', $submission->id)
                    ->update(['evidence_task_id' => $targetTaskId]);

                $affectedTaskIds[(int) $submission->evidence_task_id] = true;
                $affectedTaskIds[$targetTaskId] = true;
            }

            foreach (array_keys($affectedTaskIds) as $taskId) {
                $status = DB::table('evidence_submissions')
                    ->where('evidence_task_id', $taskId)
                    ->whereNull('deleted_at')
                    ->orderByDesc('id')
                    ->value('status');

                DB::table('evidence_tasks')
                    ->where('id', $taskId)
                    ->update([
                        'status' => $status ?: 'pending',
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function down(): void
    {
        // Evidence is not merged back because each group may continue receiving uploads.
    }

    private function offeringKey(
        int $programId,
        int $termId,
        int $courseId,
        string $resultCode,
        int $userId,
    ): string {
        return implode('|', [$programId, $termId, $courseId, $resultCode, $userId]);
    }

    private function targetTaskId(object $submission, object $targetOffering): int
    {
        $targetTask = DB::table('evidence_tasks')
            ->where('accreditation_cycle_id', $submission->accreditation_cycle_id)
            ->where('evidence_requirement_id', $submission->evidence_requirement_id)
            ->where('context_type', 'assessment_course')
            ->where('context_id', $targetOffering->id)
            ->orderBy('id')
            ->first();

        if ($targetTask) {
            if ($targetTask->deleted_at) {
                DB::table('evidence_tasks')->where('id', $targetTask->id)->update([
                    'deleted_at' => null,
                    'assigned_to' => $targetOffering->user_id,
                    'updated_at' => now(),
                ]);
            }

            return (int) $targetTask->id;
        }

        $sourceTask = DB::table('evidence_tasks')->find($submission->evidence_task_id);

        if (! $sourceTask) {
            throw new RuntimeException("Assessment task {$submission->evidence_task_id} was not found.");
        }

        $metadata = json_decode($sourceTask->metadata ?: '{}', true) ?: [];
        $metadata['assessment_group'] = $targetOffering->section;

        return (int) DB::table('evidence_tasks')->insertGetId([
            'accreditation_cycle_id' => $sourceTask->accreditation_cycle_id,
            'program_id' => $sourceTask->program_id,
            'accreditation_criterion_id' => $sourceTask->accreditation_criterion_id,
            'accreditation_subcriterion_id' => $sourceTask->accreditation_subcriterion_id,
            'evidence_requirement_id' => $sourceTask->evidence_requirement_id,
            'academic_year_id' => $sourceTask->academic_year_id,
            'academic_term_id' => $sourceTask->academic_term_id,
            'context_type' => 'assessment_course',
            'context_id' => $targetOffering->id,
            'assigned_to' => $targetOffering->user_id,
            'created_by' => $sourceTask->created_by,
            'due_date' => $sourceTask->due_date,
            'status' => $sourceTask->status,
            'priority' => $sourceTask->priority,
            'instructions' => 'Assessment '.trim(
                $targetOffering->assessment_result_code.' '.$targetOffering->assessment_result_name
            ).' - Grupo '.$targetOffering->section.'.',
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
