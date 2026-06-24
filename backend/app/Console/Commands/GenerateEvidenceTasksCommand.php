<?php

namespace App\Console\Commands;

use App\Models\AccreditationCycle;
use App\Models\CourseOffering;
use App\Models\EvidenceRequirement;
use App\Models\EvidenceTask;
use App\Models\Teacher;
use Illuminate\Console\Command;

class GenerateEvidenceTasksCommand extends Command
{
    protected $signature = 'accreditation:generate-tasks {cycle_id} {--academic_year_id=} {--academic_term_id=}';

    protected $description = 'Genera checklist de evidencias para un ciclo de acreditacion.';

    public function handle(): int
    {
        $cycle = AccreditationCycle::with(['model', 'program.faculty'])->findOrFail($this->argument('cycle_id'));
        $requirements = EvidenceRequirement::query()
            ->whereHas('criterion', fn ($query) => $query->where('accreditation_model_id', $cycle->accreditation_model_id))
            ->where('is_active', true)
            ->get();

        $processed = 0;

        foreach ($requirements as $requirement) {
            if ($requirement->applies_to === 'course_offering') {
                $processed += $this->generateCourseTasks($cycle, $requirement);
                continue;
            }

            if ($requirement->applies_to === 'assessment_course') {
                $processed += $this->generateAssessmentCourseTasks($cycle, $requirement);
                continue;
            }

            if ($requirement->applies_to === 'teacher') {
                $processed += $this->generateTeacherTasks($cycle, $requirement);
                continue;
            }

            $this->createTask($cycle, $requirement, $requirement->applies_to, $cycle->program_id);
            $processed++;
        }

        $this->info("Checklist generado. Tareas procesadas: {$processed}");

        return self::SUCCESS;
    }

    private function generateCourseTasks(AccreditationCycle $cycle, EvidenceRequirement $requirement): int
    {
        $query = CourseOffering::query()->where('program_id', $cycle->program_id);

        if ($this->option('academic_term_id')) {
            $query->where('academic_term_id', $this->option('academic_term_id'));
        }

        $count = 0;
        foreach ($query->get() as $offering) {
            $this->createTask($cycle, $requirement, 'course_offering', $offering->id, [
                'academic_term_id' => $offering->academic_term_id,
            ]);
            $count++;
        }

        return $count;
    }

    private function generateAssessmentCourseTasks(AccreditationCycle $cycle, EvidenceRequirement $requirement): int
    {
        $query = CourseOffering::query()
            ->where('program_id', $cycle->program_id)
            ->where('is_assessment_course', true);

        if ($this->option('academic_term_id')) {
            $query->where('academic_term_id', $this->option('academic_term_id'));
        }

        if ($requirement->code === 'C3-ASS-04') {
            $query->where('requires_assessment_video', true);
        }

        $count = 0;
        foreach ($query->get() as $offering) {
            $this->createTask($cycle, $requirement, 'assessment_course', $offering->id, [
                'academic_term_id' => $offering->academic_term_id,
                'instructions' => 'Assessment '.trim(($offering->assessment_result_code ?: '').' '.$offering->assessment_result_name).': '.$requirement->name.'.',
                'metadata' => [
                    'assessment_result_code' => $offering->assessment_result_code,
                    'assessment_result_name' => $offering->assessment_result_name,
                    'requires_video' => $offering->requires_assessment_video,
                ],
            ]);
            $count++;
        }

        return $count;
    }

    private function generateTeacherTasks(AccreditationCycle $cycle, EvidenceRequirement $requirement): int
    {
        $teachers = Teacher::query()
            ->where('institution_id', $cycle->program->faculty->institution_id ?? null)
            ->where('is_active', true)
            ->get();

        $count = 0;
        foreach ($teachers as $teacher) {
            $this->createTask($cycle, $requirement, 'teacher', $teacher->id, [
                'assigned_to' => $teacher->user_id,
            ]);
            $count++;
        }

        return $count;
    }

    private function createTask(AccreditationCycle $cycle, EvidenceRequirement $requirement, ?string $contextType, ?int $contextId, array $extra = []): EvidenceTask
    {
        return EvidenceTask::updateOrCreate([
            'accreditation_cycle_id' => $cycle->id,
            'evidence_requirement_id' => $requirement->id,
            'context_type' => $contextType,
            'context_id' => $contextId,
        ], array_merge([
            'program_id' => $cycle->program_id,
            'accreditation_criterion_id' => $requirement->accreditation_criterion_id,
            'accreditation_subcriterion_id' => $requirement->accreditation_subcriterion_id,
            'academic_year_id' => $this->option('academic_year_id'),
            'status' => 'pending',
            'priority' => $requirement->is_required ? 'high' : 'normal',
        ], $extra));
    }
}
