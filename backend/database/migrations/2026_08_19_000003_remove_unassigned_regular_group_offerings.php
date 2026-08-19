<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $offeringIds = DB::table('course_offerings as co')
                ->leftJoin('course_assignments as ca', 'ca.course_offering_id', '=', 'co.id')
                ->where('co.is_assessment_course', false)
                ->whereIn('co.section', ['A', 'B'])
                ->whereNull('co.deleted_at')
                ->whereNull('ca.id')
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('evidence_tasks as et')
                        ->join('evidence_submissions as es', 'es.evidence_task_id', '=', 'et.id')
                        ->whereColumn('et.context_id', 'co.id')
                        ->where('et.context_type', 'course_offering');
                })
                ->pluck('co.id');

            if ($offeringIds->isEmpty()) {
                return;
            }

            $now = now();

            DB::table('evidence_tasks')
                ->where('context_type', 'course_offering')
                ->whereIn('context_id', $offeringIds)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ]);

            DB::table('course_offerings')
                ->whereIn('id', $offeringIds)
                ->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ]);
        });
    }

    public function down(): void
    {
        // Rows without a teacher or evidence must not be recreated automatically.
    }
};
