<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $port01 = DB::table('evidence_requirements')->where('code', 'C5-PORT-01')->first();
        $port02 = DB::table('evidence_requirements')->where('code', 'C5-PORT-02')->first();

        if ($port01) {
            DB::table('evidence_requirements')
                ->where('id', $port01->id)
                ->update([
                    'name' => 'Silabo del curso, temario, sesiones de aprendizaje y cronograma de avance',
                    'updated_at' => now(),
                ]);
        }

        if (! $port01 || ! $port02) {
            return;
        }

        DB::table('evidence_submissions')
            ->where('evidence_requirement_id', $port02->id)
            ->update([
                'evidence_requirement_id' => $port01->id,
                'updated_at' => now(),
            ]);

        $port02Tasks = DB::table('evidence_tasks')
            ->where('evidence_requirement_id', $port02->id)
            ->get();

        foreach ($port02Tasks as $task) {
            $matchingPort01Task = DB::table('evidence_tasks')
                ->where('accreditation_cycle_id', $task->accreditation_cycle_id)
                ->where('program_id', $task->program_id)
                ->where('evidence_requirement_id', $port01->id)
                ->where('context_type', $task->context_type)
                ->where('context_id', $task->context_id)
                ->where('academic_term_id', $task->academic_term_id)
                ->whereNull('deleted_at')
                ->first();

            if ($matchingPort01Task) {
                DB::table('evidence_submissions')
                    ->where('evidence_task_id', $task->id)
                    ->update([
                        'evidence_task_id' => $matchingPort01Task->id,
                        'updated_at' => now(),
                    ]);

                DB::table('evidence_status_histories')
                    ->where('evidence_task_id', $task->id)
                    ->update(['evidence_task_id' => $matchingPort01Task->id]);

                if (! in_array($task->status, ['pending', 'assigned'], true)
                    && in_array($matchingPort01Task->status, ['pending', 'assigned'], true)) {
                    DB::table('evidence_tasks')
                        ->where('id', $matchingPort01Task->id)
                        ->update([
                            'status' => $task->status,
                            'updated_at' => now(),
                        ]);
                }

                DB::table('evidence_tasks')->where('id', $task->id)->delete();
                continue;
            }

            DB::table('evidence_tasks')
                ->where('id', $task->id)
                ->update([
                    'evidence_requirement_id' => $port01->id,
                    'updated_at' => now(),
                ]);
        }

        DB::table('evidence_requirements')
            ->where('id', $port02->id)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('evidence_requirements')
            ->where('code', 'C5-PORT-01')
            ->update([
                'name' => 'Silabo del curso',
                'updated_at' => now(),
            ]);

        DB::table('evidence_requirements')
            ->where('code', 'C5-PORT-02')
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};
