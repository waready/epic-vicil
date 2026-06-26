<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $criterion = DB::table('accreditation_criteria')->where('code', 'C5')->first();

        if (! $criterion) {
            return;
        }

        $subcriterionId = DB::table('accreditation_subcriteria')
            ->where('accreditation_criterion_id', $criterion->id)
            ->orderBy('order')
            ->value('id');

        DB::table('evidence_requirements')
            ->where('accreditation_criterion_id', $criterion->id)
            ->where('code', 'C5-PORT-01')
            ->update([
                'name' => 'Silabo del curso',
                'is_active' => true,
                'updated_at' => now(),
            ]);

        $port02Id = DB::table('evidence_requirements')
            ->where('accreditation_criterion_id', $criterion->id)
            ->where('code', 'C5-PORT-02')
            ->value('id');

        if ($port02Id) {
            DB::table('evidence_requirements')
                ->where('id', $port02Id)
                ->update([
                    'name' => 'Temario, sesiones de aprendizaje y cronograma de avance',
                    'applies_to' => 'course_offering',
                    'evidence_kind' => 'portfolio',
                    'is_required' => true,
                    'allows_multiple_files' => true,
                    'order' => 3,
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
        } else {
            $port02Id = DB::table('evidence_requirements')->insertGetId([
                'accreditation_criterion_id' => $criterion->id,
                'accreditation_subcriterion_id' => $subcriterionId,
                'code' => 'C5-PORT-02',
                'name' => 'Temario, sesiones de aprendizaje y cronograma de avance',
                'applies_to' => 'course_offering',
                'evidence_kind' => 'portfolio',
                'is_required' => true,
                'allows_multiple_files' => true,
                'allowed_extensions' => json_encode(config('accreditation.allowed_extensions')),
                'order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $cycles = DB::table('accreditation_cycles')
            ->whereIn('status', ['planning', 'active'])
            ->get();

        foreach ($cycles as $cycle) {
            $offerings = DB::table('course_offerings')
                ->where('program_id', $cycle->program_id)
                ->when($cycle->academic_term_id, fn ($query) => $query->where('academic_term_id', $cycle->academic_term_id))
                ->whereNull('deleted_at')
                ->get();

            foreach ($offerings as $offering) {
                $exists = DB::table('evidence_tasks')
                    ->where('accreditation_cycle_id', $cycle->id)
                    ->where('evidence_requirement_id', $port02Id)
                    ->where('context_type', 'course_offering')
                    ->where('context_id', $offering->id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($exists) {
                    continue;
                }

                $assignedTo = DB::table('course_assignments')
                    ->join('teachers', 'teachers.id', '=', 'course_assignments.teacher_id')
                    ->where('course_assignments.course_offering_id', $offering->id)
                    ->where('course_assignments.role', 'main')
                    ->value('teachers.user_id');

                DB::table('evidence_tasks')->insert([
                    'accreditation_cycle_id' => $cycle->id,
                    'program_id' => $cycle->program_id,
                    'accreditation_criterion_id' => $criterion->id,
                    'accreditation_subcriterion_id' => $subcriterionId,
                    'evidence_requirement_id' => $port02Id,
                    'academic_term_id' => $offering->academic_term_id,
                    'context_type' => 'course_offering',
                    'context_id' => $offering->id,
                    'assigned_to' => $assignedTo,
                    'status' => 'pending',
                    'priority' => 'high',
                    'instructions' => 'Portafolio de curso: Temario, sesiones de aprendizaje y cronograma de avance.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $criterion = DB::table('accreditation_criteria')->where('code', 'C5')->first();

        if (! $criterion) {
            return;
        }

        DB::table('evidence_requirements')
            ->where('accreditation_criterion_id', $criterion->id)
            ->where('code', 'C5-PORT-01')
            ->update([
                'name' => 'Silabo del curso, temario, sesiones de aprendizaje y cronograma de avance',
                'updated_at' => now(),
            ]);

        DB::table('evidence_requirements')
            ->where('accreditation_criterion_id', $criterion->id)
            ->where('code', 'C5-PORT-02')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }
};
